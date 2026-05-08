import { Toaster as Sonner } from "sonner";
import type { ComponentProps } from "react";

type ToasterProps = ComponentProps<typeof Sonner>;

const Toaster = ({ ...props }: ToasterProps) => (
  <Sonner
    className="toaster group"
    toastOptions={{
      classNames: {
        toast: "group toast",
        description: "group-[.toast]:text-muted-foreground",
      },
    }}
    {...props}
  />
);

export { Toaster };
