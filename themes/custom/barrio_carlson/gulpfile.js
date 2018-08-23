var gulp = require('gulp');
var browserSync = require('browser-sync').create();
var sass = require('gulp-sass');
var concat = require("gulp-concat");
var minifyCss = require("gulp-minify-css");
var uglify = require("gulp-uglify");

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
    files: paths.styles.destination + '**/*.css',
    file: paths.styles.destination + '/style.css',
    destination: paths.styles.destination
  },

  // ----- Sass ----- //

  sass: {
    files: paths.styles.source + '**/*.scss',
    file: paths.styles.source + 'style.scss',
    destination: paths.styles.destination
  },

  // ----- JS ----- //
  js: {
    files: paths.scripts + '**/*.js',
    destination: paths.scripts

  },

  // ----- Images ----- //
  images: {
    files: paths.images + '**/*.{png,gif,jpg,svg}',
    destination: paths.images
  },

  // ----- eslint ----- //
  jsLinting: {
    files: {
      theme: [
        paths.scripts + '**/*.js',
        '!' + paths.scripts + '**/*.min.js'
      ],
      gulp: [
        'gulpfile.js',
        'gulp-tasks/**/*'
      ]
    }

  },

  // ----- KSS Node ----- //
  styleGuide: {
    source: [
      paths.styles.source
    ],
    destination: 'styleguide/',
    css: [
      path.relative(paths.styleGuide, paths.styles.destination + 'style.css'),
      path.relative(paths.styleGuide, paths.styles.destination + 'kss-only.css'),
      "https://fonts.googleapis.com/css?family=Crimson+Text:400,600,700|Lato:300,400,700"
    ],
    js: [],
    homepage: 'style-guide-only/homepage.md',
    title: 'Living Style Guide'
  }

};

// Compile sass into CSS & auto-inject into browsers
gulp.task('sass', function() {
    return gulp.src(['node_modules/bootstrap/scss/bootstrap.scss', 'scss/style.scss'])
        .pipe(sass().on('error', sass.logError))
        .pipe(gulp.dest("css"))
        .pipe(sass({ outputStyle: 'compressed' }))
        // .pipe(minifyCss())
        .pipe(browserSync.stream());
});

// Move the javascript files into our js folder
gulp.task('js', function() {
    return gulp.src(['node_modules/bootstrap/dist/js/bootstrap.min.js', 'node_modules/jquery/dist/jquery.min.js', 'node_modules/popper.js/dist/umd/popper.min.js'])
        .pipe(gulp.dest("js"))
        .pipe(browserSync.stream());
});

// Static Server + watching scss/html files
gulp.task('serve', ['sass'], function() {

    browserSync.init({
        proxy: "http://carlsonschool8.lndo.site:8000/sites/default/themes/custom/barrio_carlson/styleguide/",
    });

    gulp.watch(['node_modules/bootstrap/scss/bootstrap.scss', 'scss/*.scss', 'scss/**/*.scss', 'scss/**/**/*.scss'], ['sass']);

    gulp.watch("src/*.html").on('change', browserSync.reload);
});

// Compile the styleguide
gulp.task('compile:styleguide', function (cb) {
    plugins.kss(options.styleGuide, cb);
});

gulp.task('default', ['js', 'compile:styleguide','serve']);
