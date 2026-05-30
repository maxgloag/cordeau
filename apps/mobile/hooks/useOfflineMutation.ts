import { useRef } from "react";
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
  const inFlight = useRef(false);

  function mutate(payload: TPayload, entityId?: string): string | undefined {
    if (inFlight.current) return undefined;
    inFlight.current = true;
    try {
      const id = entityId ?? randomUUID();
      options.buildLocal(id, payload);
      const apiPayload =
        options.operation === "create" ? { ...payload, uuid: id } : payload;
      pushToOutbox({
        entityType: options.entityType,
        entityId: id,
        operation: options.operation,
        payload: JSON.stringify(apiPayload),
      });
      void processOutbox(queryClient);
      return id;
    } finally {
      setTimeout(() => {
        inFlight.current = false;
      }, 500);
    }
  }

  return { mutate };
}
