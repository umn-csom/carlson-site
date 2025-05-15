import { generate } from 'critical';
import fs from 'fs';
import path from 'path';
import axios from 'axios';
import { fileURLToPath } from 'url';

// Get __dirname equivalent in ES modules
const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

// Configuration
const BASE_URL = 'http://carlsonschool.ddev.site';
const OUTPUT_DIR = path.join(__dirname, '../css/critical');
const DEFAULT_CRITICAL_PATH = path.join(__dirname, '../css/critical/default-critical.css');
const ERROR_DELAY = 5000; // 5 seconds delay after error
const MAX_RETRIES = 3; // Maximum number of retries per page

// Helper function to add delay
const delay = (ms) => new Promise(resolve => setTimeout(resolve, ms));

// Define content types and their representative URLs
const contentTypes = {
  alumni_default_page: "/node/114021",
  alumni_landing_page: "/node/113926",
  blog_entry: "/node/113001",
  conference: "/node/96206",
  curriculum_plan: "/node/118271",
  education_abroad_program: "/node/1916",
  email_page: "/node/104971",
  event: "/node/130411",
  executive_ed_landing_page: "/node/95871",
  executive_ed_program: "/node/72751",
  faces: "/node/103861",
  faculty_profile: "/node/54856",
  front: "/node/104681",
  landing_page: "/node/88176",
  magazine: "/node/126576",
  news: "/node/129931",
  news_landing: "/node/106836",
  page: "/node/13", //page: "/node/11771",
  person: "/node/96621",
  quiz: "/node/116591",
  session: "/node/94421",
  student: "/node/128751",
  video: "/node/107586",
};

// Ensure output directory exists
if (!fs.existsSync(OUTPUT_DIR)) {
  fs.mkdirSync(OUTPUT_DIR, { recursive: true });
}

// Generate critical CSS for each content type
async function generateCriticalCSS() {
  for (const [type, url] of Object.entries(contentTypes)) {
    console.log(`Generating critical CSS for ${type}...`);
    let retryCount = 0;
    let success = false;

    while (!success && retryCount < MAX_RETRIES) {
      try {
        const result = await generate({
          src: `${BASE_URL}${url}`,
          target: {
            css: path.join(OUTPUT_DIR, `${type}.css`),
          },
          width: 1300,
          height: 900,
          inline: false,
          ignore: {
            atrule: ["@font-face", /sticky/, "@keyframes"],
            rule: [
              ":root",
              "::-webkit-file-upload-button",
              ":-moz-focus-inner",
              /mega-menu/,
              /menu-block/,
              /focus/,
            ],
            decl: [/--mm-/, "transition", "animation"],
          },
          cleanCSS: {
            level: 2,
          },
          penthouse: {
            timeout: 120000,
            requestHeaders: {
              "User-Agent":
                "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.114 Safari/537.36",
            },
            forceInclude: [],
            keepLargerMediaQueries: true,
            renderWaitTime: 1000,
            blockJSRequests: false,
            customPageHeaders: {
              "Accept-Encoding": "gzip, deflate, br",
            },
            puppeteer: {
              args: [
                "--no-sandbox",
                "--disable-setuid-sandbox",
                "--disable-web-security",
                "--disable-dev-shm-usage",
                "--disable-gpu",
                "--disable-software-rasterizer",
              ],
              headless: "new",
            },
          },
        });

        // Convert the CSS to SCSS format
        const cssContent = `/**
 * @file
 * Critical CSS for ${type}
 *
 * Generated automatically - DO NOT EDIT DIRECTLY
 */
${result.css}`;

        fs.writeFileSync(path.join(OUTPUT_DIR, `${type}.css`), cssContent);
        const stats = fs.statSync(path.join(OUTPUT_DIR, `${type}.css`));
        const sizeInBytes = stats.size;
        const sizeInKB = (sizeInBytes / 1024).toFixed(2);
        console.log(
          `✓ Generated css/critical/${type}.css (${sizeInBytes} bytes, ${sizeInKB} KB)`,
        );
        success = true;

        // Only fetch HTML if CSS generation was successful
        try {
          const html = (await axios.get(`${BASE_URL}${url}`, { maxRedirects: 5 }))
            .data;
          const htmlPath = path.join(OUTPUT_DIR, `${type}.html`);
          fs.writeFileSync(htmlPath, html);
          console.log(`✓ HTML stored in css/critical/${type}.html`);
        } catch (error) {
          console.error(`⨉ Error generating HTML for ${type}:`, error);
        }

      } catch (error) {
        retryCount++;
        console.error(`⨉ Error generating critical CSS for ${type} (attempt ${retryCount}/${MAX_RETRIES}):`, error);

        if (retryCount < MAX_RETRIES) {
          console.log(`Waiting ${ERROR_DELAY}ms before retry...`);
          await delay(ERROR_DELAY);
        } else {
          console.error(`Failed to generate critical CSS for ${type} after ${MAX_RETRIES} attempts`);
        }
      }
    }
  }
}

// Run the generation
generateCriticalCSS().catch(console.error);
