var gulp = require('gulp');
var browserSync = require('browser-sync').create();
var sass = require('gulp-sass');
var cleanCSS = require('gulp-clean-css');
var concat = require("gulp-concat");
var sourcemaps = require("gulp-sourcemaps");
var googleWebFonts = require("gulp-google-webfonts");
var shell = require('gulp-shell');
var postcss = require('gulp-postcss');
var selectorReplace = require('postcss-selector-replace');
var remToPx = require("postcss-rem-to-pixel");

// Setting pattern this way allows non gulp- plugins to be loaded as well.
var plugins = require('gulp-load-plugins')({
  pattern: '*',
  rename: {
    'node-sass-import-once': 'importOnce',
    'gulp-sass-glob': 'sassGlob',
    'run-sequence': 'runSequence',
    'gulp-clean-css': 'cleanCSS'
  }
});

// Used to generate relative paths for style guide output.
var path = require('path');

// These are used in the options below.
var paths = {
  styles: {
    source: 'scss/',
    destination: 'css/'
  },
  scripts: 'js/',
  images: 'imgages/',
  styleGuide: 'styleguide'
};

// These are passed to each task.
var options = {
  // ----- CSS ----- //

  css: {
    files: paths.styles.destination + "**/*.css",
    file: paths.styles.destination + "/style.css",
    destination: paths.styles.destination,
  },

  // ----- Sass ----- //

  sass: {
    files: paths.styles.source + "**/*.scss",
    file: paths.styles.source + "style.scss",
    destination: paths.styles.destination,
  },

  // ----- JS ----- //
  js: {
    files: paths.scripts + "**/*.js",
    destination: paths.scripts,
  },

  // ----- Images ----- //
  images: {
    files: paths.images + "**/*.{png,gif,jpg,svg}",
    destination: paths.images,
  },

  // ----- eslint ----- //
  jsLinting: {
    files: {
      theme: [paths.scripts + "**/*.js", "!" + paths.scripts + "**/*.min.js"],
      gulp: ["gulpfile.js", "gulp-tasks/**/*"],
    },
  },

  // ----- KSS Node ----- //
  styleGuide: {
    source: [paths.styles.source],
    destination: "styleguide/",
    css: [
      path.relative(paths.styleGuide, paths.styles.destination + "style.css"),
      path.relative(
        paths.styleGuide,
        paths.styles.destination + "kss-only.css"
      ),
      "https://fonts.googleapis.com/css?family=Crimson+Text:400,600,700|Lato:300,400,700",
    ],
    js: [],
    homepage: "styleguide-dev/homepage.md",
    title: "Living Style Guide",
  },

  googleFontsOptions: {
    fontsDir: "./fonts",
    cssDir: "./",
    cssFilename: "google-fonts.css",
    fontDisplayType: "auto",
  },

  selectorReplace: {
    before: [
      ".ck-content body",
      ".ck-content html",
      ".ck-content .ck-content",
      ".ck-content .ck.ck-content",
    ],
    after: [
      ".ck-content",
      ".ck-content",
      ".ck-content",
      ".ck.ck-content"
    ],
  },

  remToPx: {
    rootValue: 10,
    propList: ["*"],
    mediaQuery: true,
  },
};

// Compile sass into CSS & auto-inject into browsers
gulp.task('sass', function() {
  return gulp.src(['scss/*.scss'], ['sass'])
        .pipe(sourcemaps.init())
        .pipe(sass().on('error', sass.logError))
        .pipe(sourcemaps.write())
        .pipe(gulp.dest("css"))
        .pipe(sass({ outputStyle: 'compressed' }))
        //.pipe(minifyCss())
        .pipe(browserSync.stream());
});

gulp.task('sass-components', function() {
  return gulp.src(['scss/components/**/*.scss'], ['sass'])
    .pipe(sourcemaps.init())
    .pipe(sass().on('error', sass.logError))
    .pipe(sourcemaps.write())
    .pipe(gulp.dest("css/components"))
    .pipe(sass({ outputStyle: 'compressed' }))
    //.pipe(minifyCss())
    .pipe(browserSync.stream());
});

gulp.task('css', function() {
  var processors = [
    selectorReplace(options.selectorReplace),
    remToPx(options.remToPx)
  ];
  return gulp.src('css/ckeditor-style.css')
    .pipe(postcss(processors))
    .pipe(gulp.dest('css/ckeditor'));
})

gulp.task('minify-css', () => {
  return gulp.src('css/*.css')
    .pipe(cleanCSS({compatibility: 'ie8'}))
    .pipe(gulp.dest('dist'));
});

// Move the javascript files into our js folder
gulp.task('js', function() {
    return gulp.src(['node_modules/bootstrap/dist/js/bootstrap.min.js', 'node_modules/jquery/dist/jquery.min.js', 'node_modules/popper.js/dist/umd/popper.min.js'])
      .pipe(gulp.dest("js"))
      .pipe(browserSync.stream());
});

gulp.task('font', () => {
  return gulp.src('./fonts.list')
    .pipe(googleWebFonts(options.googleFontsOptions))
    .pipe(gulp.dest("css"));
});

// Static Server + watching scss/html files
gulp.task('serve', ['sass', 'sass-components'], function() {

    browserSync.init({
        proxy: "https://carlson.lndo.site",
    });

    gulp.start('watch');
});

gulp.task('watch', ['sass', 'sass-components'], function() {
  gulp.watch(
    [
      'node_modules/bootstrap/scss/bootstrap.scss',
      'scss/*.scss',
      'scss/**/*.scss',
      'scss/**/**/*.scss',
      'templates/components/*.twig',
    ],
    ['sass', 'sass-components']
  );
});

// Compile the styleguide
gulp.task('compile:styleguide', function (cb) {
    plugins.kss(options.styleGuide, cb);
});

// Refresh SASS files.
gulp.task('refresh-sass', shell.task('npm run kss'));

// Default.
gulp.task('default', ['js','sass','sass-components','css','minify-css','watch']);
