/**
 * Minify every compiled .js file inside dist/ in place.
 * TypeScript does not minify; this step runs after tsc.
 */
import { build } from "esbuild";
import { readdir } from "node:fs/promises";
import { join } from "node:path";

/**
 * Recursively collect JavaScript files under a directory.
 */
async function listJsFiles(directory) {
  const entries = await readdir(directory, { withFileTypes: true });
  const files = [];

  for (const entry of entries) {
    const fullPath = join(directory, entry.name);

    if (entry.isDirectory()) {
      files.push(...(await listJsFiles(fullPath)));
      continue;
    }

    if (entry.name.endsWith(".js")) {
      files.push(fullPath);
    }
  }

  return files;
}

const jsFiles = await listJsFiles("dist");

await Promise.all(
  jsFiles.map((file) =>
    build({
      entryPoints: [file],
      outfile: file,
      allowOverwrite: true,
      bundle: false,
      minify: true,
      legalComments: "none",
      platform: "node",
      format: "cjs",
      target: "es2023",
      sourcemap: true,
    })
  )
);

console.log(`Minified ${jsFiles.length} file(s) in dist/`);
