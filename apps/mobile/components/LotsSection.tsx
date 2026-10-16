import { useState } from "react";
import { Alert, Text, TextInput, TouchableOpacity, View } from "react-native";
import { Check, Plus, Trash2, X } from "lucide-react-native";
import { useLots } from "@/hooks/useLots";
import { useTaches } from "@/hooks/useTaches";
import type { Lot, ModeFacturation, Tache } from "@/lib/api";
import {
  MODES,
  lireEstimation,
  lotSchema,
  tacheLibelleSchema,
  uniteDuMode,
} from "@/lib/lot";

/**
 * Section « Lots » de l'écran chantier. Mode express : zéro lot est un état normal,
 * le chantier reste pleinement utilisable sans.
 */
export function LotsSection({ chantierId }: { chantierId: string }) {
  const { lots, ajouterLot, supprimerLot } = useLots(chantierId);
  const [creation, setCreation] = useState(false);

  function confirmerSuppression(lot: Lot): void {
    Alert.alert(
      `Supprimer « ${lot.nom} » ?`,
      "Ses tâches seront supprimées aussi.",
      [
        { text: "Annuler", style: "cancel" },
        {
          text: "Supprimer",
          style: "destructive",
          onPress: () => supprimerLot(lot.id),
        },
      ],
    );
  }

  return (
    <View className="mt-6">
      <View className="flex-row items-center justify-between mb-3 px-4">
        <Text className="text-lg font-semibold text-gray-900">Lots</Text>
        <TouchableOpacity
          accessibilityLabel="Ajouter un lot"
          onPress={() => setCreation((v) => !v)}
          className="bg-blue-600 rounded-full w-8 h-8 items-center justify-center"
        >
          <Plus size={18} color="#FFFFFF" />
        </TouchableOpacity>
      </View>

      {creation && (
        <NouveauLotForm
          onAnnuler={() => setCreation(false)}
          onValider={(lot) => {
            ajouterLot(lot);
            setCreation(false);
          }}
        />
      )}

      {lots.length === 0 && !creation && (
        <Text className="mx-4 text-sm text-gray-400">
          Aucun lot : le chantier fonctionne très bien sans. Ajoute des lots
          pour suivre le travail par poste.
        </Text>
      )}

      <View className="gap-3 px-4">
        {lots.map((lot) => (
          <LotCard
            key={lot.id}
            lot={lot}
            onSupprimer={() => confirmerSuppression(lot)}
          />
        ))}
      </View>
    </View>
  );
}

function NouveauLotForm({
  onAnnuler,
  onValider,
}: {
  onAnnuler: () => void;
  onValider: (lot: {
    nom: string;
    mode: ModeFacturation;
    estimation: number | null;
  }) => void;
}) {
  const [nom, setNom] = useState("");
  const [mode, setMode] = useState<ModeFacturation>("temps");
  const [estimation, setEstimation] = useState("");
  const [erreur, setErreur] = useState<string | null>(null);

  function valider(): void {
    const resultat = lotSchema.safeParse({ nom, mode, estimation });
    if (!resultat.success) {
      setErreur(resultat.error.issues[0]?.message ?? "Saisie invalide");
      return;
    }
    onValider({
      nom: nom.trim(),
      mode,
      estimation: lireEstimation(estimation),
    });
  }

  return (
    <View className="mx-4 mb-3 bg-surface rounded-xl border border-border p-4 gap-3">
      <TextInput
        value={nom}
        onChangeText={setNom}
        placeholder="Nom du lot (ex. Démolition)"
        placeholderTextColor="#9CA3AF"
        autoFocus
        className="text-base text-text border-b border-border pb-1"
      />
      <View className="flex-row gap-2">
        {MODES.map((m) => (
          <TouchableOpacity
            key={m.valeur}
            onPress={() => setMode(m.valeur)}
            className={`rounded-full px-3 py-1.5 border ${
              mode === m.valeur
                ? "bg-blue-600 border-blue-600"
                : "border-border"
            }`}
          >
            <Text
              className={`text-sm ${
                mode === m.valeur ? "text-white" : "text-muted"
              }`}
            >
              {m.label}
            </Text>
          </TouchableOpacity>
        ))}
      </View>
      <View className="flex-row items-center gap-2">
        <TextInput
          value={estimation}
          onChangeText={setEstimation}
          placeholder="Estimation (facultatif)"
          placeholderTextColor="#9CA3AF"
          keyboardType="decimal-pad"
          className="flex-1 text-base text-text border-b border-border pb-1"
        />
        <Text className="text-base text-muted">{uniteDuMode(mode)}</Text>
      </View>
      {erreur && <Text className="text-sm text-red-600">{erreur}</Text>}
      <View className="flex-row gap-3">
        <TouchableOpacity
          onPress={onAnnuler}
          className="flex-1 border border-border rounded-xl py-3 items-center"
        >
          <Text className="text-base text-muted">Annuler</Text>
        </TouchableOpacity>
        <TouchableOpacity
          onPress={valider}
          className="flex-1 bg-blue-600 rounded-xl py-3 items-center"
        >
          <Text className="text-base text-white font-medium">Ajouter</Text>
        </TouchableOpacity>
      </View>
    </View>
  );
}

function LotCard({ lot, onSupprimer }: { lot: Lot; onSupprimer: () => void }) {
  const { taches, ajouterTache, basculerTache, renommerTache, supprimerTache } =
    useTaches(lot.id);
  const [brouillon, setBrouillon] = useState("");
  const faites = taches.filter((t) => t.faite).length;
  const mode = MODES.find((m) => m.valeur === lot.mode);

  function ajouter(): void {
    const libelle = brouillon.trim();
    if (!tacheLibelleSchema.safeParse(libelle).success) return;
    ajouterTache(libelle);
    setBrouillon("");
  }

  return (
    <View className="bg-surface rounded-xl border border-border p-4">
      <View className="flex-row items-start justify-between mb-1">
        <View className="flex-1 pr-2">
          <Text className="text-base font-semibold text-text">{lot.nom}</Text>
          <Text className="text-sm text-muted">
            {mode?.label}
            {lot.estimation != null &&
              ` · ${lot.estimation} ${uniteDuMode(lot.mode)}`}
            {taches.length > 0 && ` · ${faites}/${taches.length} faites`}
          </Text>
        </View>
        <TouchableOpacity
          accessibilityLabel={`Supprimer le lot ${lot.nom}`}
          onPress={onSupprimer}
          hitSlop={8}
        >
          <Trash2 size={18} color="#6B6259" />
        </TouchableOpacity>
      </View>

      {taches.map((tache) => (
        <TacheRow
          key={tache.id}
          tache={tache}
          onBasculer={() => basculerTache(tache)}
          onRenommer={(libelle) => renommerTache(tache.id, libelle)}
          onSupprimer={() => supprimerTache(tache.id)}
        />
      ))}

      <TextInput
        value={brouillon}
        onChangeText={setBrouillon}
        onSubmitEditing={ajouter}
        blurOnSubmit={false}
        returnKeyType="done"
        placeholder="Ajouter une tâche"
        placeholderTextColor="#9CA3AF"
        className="text-base text-text border-b border-border pb-1 mt-3"
      />
    </View>
  );
}

function TacheRow({
  tache,
  onBasculer,
  onRenommer,
  onSupprimer,
}: {
  tache: Tache;
  onBasculer: () => void;
  onRenommer: (libelle: string) => void;
  onSupprimer: () => void;
}) {
  const [edition, setEdition] = useState<string | null>(null);

  function terminerEdition(): void {
    if (edition === null) return;
    const libelle = edition.trim();
    if (
      libelle !== tache.libelle &&
      tacheLibelleSchema.safeParse(libelle).success
    ) {
      onRenommer(libelle);
    }
    setEdition(null);
  }

  return (
    <View className="flex-row items-center gap-3 py-2">
      <TouchableOpacity
        accessibilityRole="checkbox"
        accessibilityState={{ checked: tache.faite }}
        accessibilityLabel={tache.libelle}
        onPress={onBasculer}
        hitSlop={8}
        className={`w-6 h-6 rounded border items-center justify-center ${
          tache.faite ? "bg-blue-600 border-blue-600" : "border-gray-400"
        }`}
      >
        {tache.faite && <Check size={16} color="#FFFFFF" />}
      </TouchableOpacity>

      {edition === null ? (
        <TouchableOpacity
          className="flex-1"
          onPress={() => setEdition(tache.libelle)}
        >
          <Text
            className={`text-base ${
              tache.faite ? "text-gray-400 line-through" : "text-text"
            }`}
          >
            {tache.libelle}
          </Text>
        </TouchableOpacity>
      ) : (
        <TextInput
          value={edition}
          onChangeText={setEdition}
          onBlur={terminerEdition}
          onSubmitEditing={terminerEdition}
          autoFocus
          returnKeyType="done"
          className="flex-1 text-base text-text border-b border-border"
        />
      )}

      <TouchableOpacity
        accessibilityLabel={`Supprimer la tâche ${tache.libelle}`}
        onPress={onSupprimer}
        hitSlop={8}
      >
        <X size={18} color="#9CA3AF" />
      </TouchableOpacity>
    </View>
  );
}
