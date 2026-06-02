import { Fragment } from "react";
import { Icon } from "./Icon";
import { cn } from "@/lib/cn";

export interface StepperProps {
  steps: string[];
  current: number;
}

export function Stepper({ steps, current }: StepperProps) {
  return (
    <div className="stepper">
      {steps.map((label, i) => (
        <Fragment key={i}>
          {i > 0 && <div className={cn("step-line", i <= current && "done")} />}
          <div
            className={cn(
              "step",
              i < current ? "done" : i === current ? "current" : "",
            )}
          >
            <span className="step-dot">
              {i < current ? <Icon name="check" size={16} /> : i + 1}
            </span>
            <span className="step-label">{label}</span>
          </div>
        </Fragment>
      ))}
    </div>
  );
}
