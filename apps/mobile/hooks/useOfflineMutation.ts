import { useQueryClient } from "@tanstack/react-query";
import { randomUUID } from "expo-crypto";
import { pushToOutbox, processOutbox } from "@/db/outbox";
import type { OutboxEntityType, OutboxOperation } from "@/db/schema";

type Options<TPayload> = {
  entityType: OutboxEntityType;
  operation: OutboxOperation;
  buildLocal: (entityId: string, payload: TPayload) => void;
};

export function useOfflineMutation<TPayload extends Record<string, unknown>>(
  options: Options<TPayload>,
) {
  const queryClient = useQueryClient();

  function mutate(payload: TPayload, entityId?: string): string {
    const id = entityId ?? randomUUID();
    options.buildLocal(id, payload);
    pushToOutbox({
      entityType: options.entityType,
      entityId: id,
      operation: options.operation,
      payload: JSON.stringify(payload),
    });
    void processOutbox(queryClient);
    return id;
  }

  return { mutate };
}
