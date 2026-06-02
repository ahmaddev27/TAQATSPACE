"use client";

import { useId, useRef, useState, type DragEvent } from "react";
import { Icon, type IconName } from "./Icon";
import { cn } from "@/lib/cn";

export interface FileDropzoneProps {
  value?: File | null;
  onChange: (file: File | null) => void;
  icon?: IconName;
  label: string;
  hint?: string;
  accept?: string;
  /** Localized "remove" label for the clear button. */
  clearLabel?: string;
  disabled?: boolean;
}

const DEFAULT_ACCEPT = "image/png,image/jpeg,image/jpg,application/pdf";

export function FileDropzone({
  value,
  onChange,
  icon = "upload",
  label,
  hint,
  accept = DEFAULT_ACCEPT,
  clearLabel = "×",
  disabled,
}: FileDropzoneProps) {
  const inputRef = useRef<HTMLInputElement>(null);
  const [dragOver, setDragOver] = useState(false);
  const id = useId();

  function handleFiles(files: FileList | null) {
    const file = files?.[0] ?? null;
    onChange(file);
  }

  function onDrop(e: DragEvent<HTMLLabelElement>) {
    e.preventDefault();
    setDragOver(false);
    if (disabled) return;
    handleFiles(e.dataTransfer.files);
  }

  if (value) {
    return (
      <div className="upload has-file" data-filled="true">
        <Icon name="checkCircle" />
        <span style={{ fontWeight: 600, color: "var(--text-2)" }}>{value.name}</span>
        <span style={{ fontSize: "var(--fs-xs)" }}>
          {(value.size / 1024 / 1024).toFixed(2)} MB
        </span>
        <button
          type="button"
          className="link"
          style={{ marginTop: 4 }}
          onClick={() => onChange(null)}
          disabled={disabled}
        >
          {clearLabel}
        </button>
      </div>
    );
  }

  return (
    <label
      htmlFor={id}
      className={cn("upload", dragOver && "is-dragover")}
      onDragOver={(e) => {
        e.preventDefault();
        if (!disabled) setDragOver(true);
      }}
      onDragLeave={() => setDragOver(false)}
      onDrop={onDrop}
    >
      <Icon name={icon} />
      <span style={{ fontWeight: 600, color: "var(--text-2)" }}>{label}</span>
      {hint && <span style={{ fontSize: "var(--fs-xs)" }}>{hint}</span>}
      <input
        id={id}
        ref={inputRef}
        type="file"
        accept={accept}
        hidden
        disabled={disabled}
        onChange={(e) => handleFiles(e.target.files)}
      />
    </label>
  );
}
