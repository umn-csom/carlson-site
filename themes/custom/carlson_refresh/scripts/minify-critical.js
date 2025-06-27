import fs from 'fs';
import path from 'path';
import postcss from 'postcss';
import cssnano from 'cssnano';
import glob from 'glob';
import { fileURLToPath } from 'url';
import safeParser from 'postcss-safe-parser';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

const CRITICAL_DIR = path.join(__dirname, '../css/critical');

// Function to normalize CSS before processing
function normalizeCSS(css) {
  // Remove BOM and normalize line endings
  css = css.replace(/^\uFEFF/, '').replace(/\r\n/g, '\n');

  // Remove comments
  css = css.replace(/\/\*[\s\S]*?\*\//g, '');

  // Normalize whitespace
  css = css.replace(/\s+/g, ' ');

  // Fix common syntax issues
  css = css.replace(/: :/g, '::');
  css = css.replace(/\.clearfix: : after/g, '.clearfix::after');

  return css;
}

// Function to parse and organize CSS rules
function parseCSSRules(css) {
  const rules = new Map();
  const mediaQueries = new Map();

  // First, extract and process media queries
  const mediaQueryRegex = /@media[^{]+{([^}]+)}/g;
  let mediaMatch;

  while ((mediaMatch = mediaQueryRegex.exec(css)) !== null) {
    const fullMatch = mediaMatch[0];
    const mediaQuery = mediaMatch[0].match(/@media[^{]+/)[0].trim();
    const mediaContent = mediaMatch[1].trim();

    // Parse rules within media query
    const mediaRules = new Map();
    const ruleRegex = /([^{]+){([^}]+)}/g;
    let ruleMatch;

    while ((ruleMatch = ruleRegex.exec(mediaContent)) !== null) {
      const selectors = ruleMatch[1].trim();
      const declarations = ruleMatch[2].trim();

      const ruleDeclarations = declarations.split(';')
        .map(d => d.trim())
        .filter(d => d)
        .map(d => {
          const [prop, value] = d.split(':').map(s => s.trim());
          return { prop, value };
        });

      const key = JSON.stringify(ruleDeclarations);
      if (!mediaRules.has(key)) {
        mediaRules.set(key, {
          selectors: new Set(),
          declarations: ruleDeclarations
        });
      }
      selectors.split(',').forEach(selector =>
        mediaRules.get(key).selectors.add(selector.trim())
      );
    }

    mediaQueries.set(mediaQuery, mediaRules);

    // Remove the processed media query from the CSS
    css = css.replace(fullMatch, '');
  }

  // Process remaining regular rules
  const ruleRegex = /([^{]+){([^}]+)}/g;
  let ruleMatch;

  while ((ruleMatch = ruleRegex.exec(css)) !== null) {
    const selectors = ruleMatch[1].trim();
    const declarations = ruleMatch[2].trim();

    // Skip if this is a media query (shouldn't happen, but just in case)
    if (selectors.startsWith('@media')) continue;

    const ruleDeclarations = declarations.split(';')
      .map(d => d.trim())
      .filter(d => d)
      .map(d => {
        const [prop, value] = d.split(':').map(s => s.trim());
        return { prop, value };
      });

    const key = JSON.stringify(ruleDeclarations);
    if (!rules.has(key)) {
      rules.set(key, {
        selectors: new Set(),
        declarations: ruleDeclarations
      });
    }
    selectors.split(',').forEach(selector =>
      rules.get(key).selectors.add(selector.trim())
    );
  }

  return { rules, mediaQueries };
}

// Function to combine and optimize CSS rules
function optimizeCSS(rules, mediaQueries) {
  let output = '';

  // Process regular rules
  for (const [_, rule] of rules) {
    const selectors = Array.from(rule.selectors).join(',\n');
    const declarations = rule.declarations
      .map(d => `  ${d.prop}: ${d.value}`)
      .join(';\n');

    output += `${selectors} {\n${declarations};\n}\n\n`;
  }

  // Process media queries
  for (const [query, queryRules] of mediaQueries) {
    let mediaOutput = '';

    for (const [_, rule] of queryRules) {
      const selectors = Array.from(rule.selectors).join(',\n  ');
      const declarations = rule.declarations
        .map(d => `    ${d.prop}: ${d.value}`)
        .join(';\n');

      mediaOutput += `  ${selectors} {\n${declarations};\n  }\n`;
    }

    if (mediaOutput) {
      output += `${query} {\n${mediaOutput}}\n\n`;
    }
  }

  return output;
}

async function processCSSFiles() {
  try {
    const files = glob.sync(path.join(CRITICAL_DIR, '*.css'));

    for (const file of files) {
      // Skip if it's already a minified file
      if (file.endsWith('.min.css')) {
        console.log(`Skipping ${path.basename(file)} (already minified)`);
        continue;
      }

      console.log(`Processing ${path.basename(file)}...`);

      // Read and normalize CSS
      let css = fs.readFileSync(file, 'utf8');
      css = normalizeCSS(css);

      // Parse and organize rules
      const { rules, mediaQueries } = parseCSSRules(css);

      // Optimize and combine rules
      let optimizedCSS = optimizeCSS(rules, mediaQueries);

      try {
        // Minify using postcss and cssnano
        const result = await postcss([
          cssnano({
            preset: ['default', {
              discardComments: { removeAll: true },
              normalizeWhitespace: true,
              colormin: true,
              minifyFontValues: true,
              minifyGradients: true,
              minifyParams: true,
              minifySelectors: true,
              mergeLonghand: true,
              mergeRules: true,
              reduceIdents: true,
              reduceInitial: true,
              reduceTransforms: true,
              uniqueSelectors: true,
              zindex: true
            }]
          })
        ]).process(optimizedCSS, {
          from: undefined,
          parser: safeParser
        });

        // Create the minified filename
        const minifiedFile = file.replace('.css', '.min.css');

        // Write the processed CSS to the new file
        fs.writeFileSync(minifiedFile, result.css);
        console.log(`✓ Created ${path.basename(minifiedFile)}`);

      } catch (processError) {
        console.error(`Error processing ${path.basename(file)}:`, processError.message);
        if (processError.input) {
          console.error('\nProblematic CSS:');
          console.error('----------------------------------------');
          console.error(processError.input.slice(Math.max(0, processError.line - 3), processError.line + 3));
          console.error('----------------------------------------');
        }
        continue;
      }
    }

    console.log('\nAll critical CSS files have been processed!');
  } catch (error) {
    console.error('Error processing CSS files:', error);
  }
}

// Run the processing
processCSSFiles();
