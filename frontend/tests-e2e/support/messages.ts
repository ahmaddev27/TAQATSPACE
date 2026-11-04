import fs from "node:fs";
import path from "node:path";

const MESSAGES_DIR = path.join(__dirname, "..", "..", "src", "messages", "en");

const cache = new Map<string, Record<string, unknown>>();

function loadFile(file: string): Record<string, unknown> {
  let content = cache.get(file);
  if (!content) {
    // "base" is the shared messages/en.json bundle (auth, nav, ...); the rest are namespace files.
    const location = file === "base" ? path.join(MESSAGES_DIR, "..", "en.json") : path.join(MESSAGES_DIR, `${file}.json`);
    content = JSON.parse(fs.readFileSync(location, "utf8")) as Record<string, unknown>;
    cache.set(file, content);
  }
  return content;
}

/**
 * The English UI string for a message key, read from the app's own message
 * files so a reworded label cannot silently desynchronise the tests.
 * `file` is the namespace file (e.g. "owner"); `key` the dotted path inside it
 * (e.g. "owner.settings.tabGeneral").
 */
export function en(file: string, key: string, values: Record<string, string | number> = {}): string {
  let node: unknown = loadFile(file);
  for (const part of key.split(".")) {
    node = (node as Record<string, unknown> | undefined)?.[part];
  }
  if (typeof node !== "string") throw new Error(`Missing English message ${file}:${key}`);
  return Object.entries(values).reduce((text, [name, value]) => text.replace(`{${name}}`, String(value)), node);
}
