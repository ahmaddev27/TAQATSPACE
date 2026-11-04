import { createHmac } from "node:crypto";

const BASE32 = "ABCDEFGHIJKLMNOPQRSTUVWXYZ234567";
const STEP_SECONDS = 30;

function decodeBase32(secret: string): Buffer {
  const clean = secret.replace(/[\s=]/g, "").toUpperCase();
  let bits = "";
  for (const char of clean) {
    const value = BASE32.indexOf(char);
    if (value < 0) throw new Error("Invalid base32 character in the two-factor secret.");
    bits += value.toString(2).padStart(5, "0");
  }
  const bytes: number[] = [];
  for (let i = 0; i + 8 <= bits.length; i += 8) bytes.push(parseInt(bits.slice(i, i + 8), 2));
  return Buffer.from(bytes);
}

/** RFC 6238 TOTP (SHA-1, 6 digits, 30 s), the default every authenticator app uses. */
export function totp(secret: string, atMs = Date.now()): string {
  const counter = Math.floor(atMs / 1000 / STEP_SECONDS);
  const message = Buffer.alloc(8);
  message.writeBigUInt64BE(BigInt(counter));
  const digest = createHmac("sha1", decodeBase32(secret)).update(message).digest();
  const offset = digest[digest.length - 1] & 0x0f;
  const binary = digest.readUInt32BE(offset) & 0x7fffffff;
  return String(binary % 1_000_000).padStart(6, "0");
}

/**
 * A code that differs from the last one used, waiting for the next time step if
 * needed: the server may reject a code it has already accepted.
 */
export async function freshTotp(secret: string, previous?: string): Promise<string> {
  let code = totp(secret);
  while (code === previous) {
    await new Promise((resolve) => setTimeout(resolve, 1000));
    code = totp(secret);
  }
  return code;
}
