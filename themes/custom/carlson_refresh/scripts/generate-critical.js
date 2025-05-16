import { generate } from 'critical';
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

// Get __dirname equivalent in ES modules
const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

// Configuration
const BASE_URL = 'http://carlsonschool.ddev.site';
const OUTPUT_DIR = path.join(__dirname, '../scss/critical');
const DEFAULT_CRITICAL_PATH = path.join(__dirname, '../scss/critical/default-critical.scss');
const ERROR_DELAY = 5000; // 5 seconds delay after error
const MAX_RETRIES = 3; // Maximum number of retries per page

// Helper function to add delay
const delay = (ms) => new Promise(resolve => setTimeout(resolve, ms));

// Define Critical CSS filename suggestions and their representative URLs
const criticalUrlSuggestions = {
  // Content types
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
  page: "/node/101191", //page: "/node/11771",
  person: "/node/96621",
  quiz: "/node/116591",
  session: "/node/94421",
  student: "/node/128751",
  video: "/node/107586",

  // Views pages.
  "path-graduate": "/graduate",
  "path-news": "/news",
  "path-faculty-research-directory-tenured-tenure-track":
    "/faculty-research/directory-tenured-tenure-track",

  // Linked from header or footer.
  "path-contact": "/contact",
  "path-contact-media": "/contact/media",
  "path-give": "/give",
  "path-location-facilities": "/location-facilities",

  // Special pages.
  "path-graduate-resources-compare-programs":
    "/graduate/resources/compare-programs",
  "path-undergraduate": "/undergraduate",
  "path-undergraduate-admissions": "/undergraduate/admissions",
  "path-undergraduate-majors-minors": "/undergraduate/majors-minors",
  "path-undergraduate-tuition-aid": "/undergraduate/tuition-aid",
  "path-undergraduate-student-life": "/undergraduate/student-life",
  "path-undergraduate-academics": "/undergraduate/academics",
  "path-undergraduate-careers": "/undergraduate/careers",
  "path-undergraduate-admissions-freshman-students":
    "/undergraduate/admissions/freshman-students",
  "path-undergraduate-admissions-transfer-students":
    "/undergraduate/admissions/transfer-students",
  "path-undergraduate-admissions-international-students":
    "/undergraduate/admissions/international-students",
  "path-undergraduate-admissions-returning-students":
    "/undergraduate/admissions/returning-students",
  "path-undergraduate-admissions-class-profile":
    "/undergraduate/admissions/class-profile",
  "path-undergraduate-student-life-ambassadors":
    "/undergraduate/student-life/ambassadors",

  // Miscellaneous.
  "path-user-login": "/user/login",
};

// Ensure output directory exists
if (!fs.existsSync(OUTPUT_DIR)) {
  fs.mkdirSync(OUTPUT_DIR, { recursive: true });
}

// Generate critical CSS for each URL suggestion
async function generateCriticalCSS() {
  for (const [type, url] of Object.entries(criticalUrlSuggestions)) {
    console.log(`Generating critical CSS for ${type}...`);
    let retryCount = 0;
    let success = false;

    while (!success && retryCount < MAX_RETRIES) {
      try {
        const result = await generate({
          src: `${BASE_URL}${url}`,
          target: {
            css: path.join(OUTPUT_DIR, `${type}.scss`),
          },
          width: 1400,
          height: 900,
          inline: false,
          ignore: {
            atrule: ["@font-face", /sticky/, "@keyframes"],
            rule: [
              ":root",
              "::-webkit-file-upload-button",
              ":-moz-focus-inner",
              "html",
              "body",
              "button",
              "article",
              "header",
              "main",
              "nav",
              "section",
              "img",
              "svg",
              "p",
              "p:last-child",
              "a",
              "a:hover",
              "a:not([href]):not([class])",
              "ul ul",
              "ul li",
              "ol li",
              ".ff-sans-serif",
              ".align-center",
              ".text-uppercase",
              ".text-capitalize",
              ".img-fluid",
              ".clearfix::after",
              ".umn-search-form",
              /crumbs/,
              /dropdown/,
              /dropright/,
              /dropleft/,
              /focus/,
              /h[1-6]/,
              /list-inline/,
              /mega-menu/,
              /menu-block/,
              /mm-menu/,
              /off-canvas/,
              /off-canvas-wrapper/,
              /site-branding/,
              /site-nav/,
              /skip-link/,
              /sr-only/,
              /umnhf-h/,
              /visually-hidden/,
            ],
            decl: [
              /\-\-mm-/,
              "transition",
              "animation",
              "box-sizing",
              "-webkit-appearance",
            ],
          },
          cleanCSS: {
            level: 2,
            //format: "beautify",
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


        const scssContent = `/**
 * @file
 * Critical CSS for ${type}
 *
 * Generated automatically - DO NOT EDIT DIRECTLY
 */
@import "base-critical";
${result.css}`;

        fs.writeFileSync(path.join(OUTPUT_DIR, `${type}.scss`), scssContent);
        const stats = fs.statSync(path.join(OUTPUT_DIR, `${type}.scss`));
        const sizeInBytes = stats.size;
        const sizeInKB = (sizeInBytes / 1024).toFixed(2);
        console.log(
          `✓ Generated scss/critical/${type}.scss (${sizeInBytes} bytes, ${sizeInKB} KB)`,
        );
        success = true;

        // Only fetch HTML if CSS generation was successful
        // try {
        //   const html = (await axios.get(`${BASE_URL}${url}`, { maxRedirects: 5 }))
        //     .data;
        //   const htmlPath = path.join(OUTPUT_DIR, `${type}.html`);
        //   fs.writeFileSync(htmlPath, html);
        //   console.log(`✓ HTML stored in css/critical/${type}.html`);
        // } catch (error) {
        //   console.error(`⨉ Error generating HTML for ${type}:`, error);
        // }
      } catch (error) {
        retryCount++;
        console.error(
          `⨉ Error generating critical CSS for ${type} (attempt ${retryCount}/${MAX_RETRIES}):`,
          error,
        );

        if (retryCount < MAX_RETRIES) {
          console.log(`Waiting ${ERROR_DELAY}ms before retry...`);
          await delay(ERROR_DELAY);
        } else {
          console.error(
            `Failed to generate critical CSS for ${type} after ${MAX_RETRIES} attempts`,
          );
        }
      }
    }
  }
}

// Run the generation
generateCriticalCSS().catch(console.error);
