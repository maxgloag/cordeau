import { Alert } from "react-native";
import * as ImagePicker from "expo-image-picker";
import { usePhotoCapture } from "@/hooks/usePhotoCapture";

const mockInsertRun = jest.fn();
const mockInsert = jest.fn(() => ({
  values: jest.fn(() => ({ run: mockInsertRun })),
}));
const mockPushToPhotoOutbox = jest.fn();
const mockProcessPhotoOutbox = jest.fn();

jest.mock("@/db", () => ({ db: { insert: () => mockInsert() } }));
jest.mock("@/db/schema", () => ({ photos: {} }));
jest.mock("@/db/photoOutbox", () => ({
  pushToPhotoOutbox: () => mockPushToPhotoOutbox(),
  processPhotoOutbox: () => mockProcessPhotoOutbox(),
}));
jest.mock("expo-crypto", () => ({ randomUUID: () => "uuid-test" }));
jest.mock("@tanstack/react-query", () => ({
  useQueryClient: () => ({ invalidateQueries: jest.fn() }),
}));
jest.mock("expo-image-picker", () => ({
  requestCameraPermissionsAsync: jest.fn(),
  requestMediaLibraryPermissionsAsync: jest.fn(),
  launchCameraAsync: jest.fn(),
  launchImageLibraryAsync: jest.fn(),
}));

const granted = { granted: true } as ImagePicker.PermissionResponse;
const denied = { granted: false } as ImagePicker.PermissionResponse;
const oneAsset = {
  canceled: false,
  assets: [{ uri: "file:///tmp/photo.jpg" }],
} as unknown as ImagePicker.ImagePickerResult;

// usePhotoCapture n'utilise aucun hook React à état (useQueryClient est mocké en
// fonction pure) : on peut l'appeler directement, sans renderer.
function setup() {
  return usePhotoCapture("chantier-1");
}

beforeEach(() => {
  jest.clearAllMocks();
  jest.spyOn(Alert, "alert").mockImplementation(() => {});
});

describe("usePhotoCapture — garde de permission avant le picker", () => {
  it("caméra refusée : n'ouvre pas le picker, alerte, n'insère rien", async () => {
    jest
      .mocked(ImagePicker.requestCameraPermissionsAsync)
      .mockResolvedValue(denied);

    await setup().captureFromCamera();

    expect(ImagePicker.launchCameraAsync).not.toHaveBeenCalled();
    expect(Alert.alert).toHaveBeenCalled();
    expect(mockInsert).not.toHaveBeenCalled();
  });

  it("caméra accordée : ouvre le picker et insère la photo capturée", async () => {
    jest
      .mocked(ImagePicker.requestCameraPermissionsAsync)
      .mockResolvedValue(granted);
    jest.mocked(ImagePicker.launchCameraAsync).mockResolvedValue(oneAsset);

    await setup().captureFromCamera();

    expect(ImagePicker.launchCameraAsync).toHaveBeenCalled();
    expect(mockInsert).toHaveBeenCalledTimes(1);
    expect(mockPushToPhotoOutbox).toHaveBeenCalledTimes(1);
    expect(Alert.alert).not.toHaveBeenCalled();
  });

  it("galerie refusée : n'ouvre pas le picker, alerte, n'insère rien", async () => {
    jest
      .mocked(ImagePicker.requestMediaLibraryPermissionsAsync)
      .mockResolvedValue(denied);

    await setup().captureFromGallery();

    expect(ImagePicker.launchImageLibraryAsync).not.toHaveBeenCalled();
    expect(Alert.alert).toHaveBeenCalled();
    expect(mockInsert).not.toHaveBeenCalled();
  });
});
