const gulp = require('gulp');
const concat = require('gulp-concat');
const streamqueue = require('streamqueue');
const uglify = require('gulp-uglify');
const cssPurge = require('gulp-css-purge');

// Concatenate and minify scripts
gulp.task('scripts', function() {
    // inspired by https://stackoverflow.com/a/23507836
    return streamqueue({ objectMode: true },
        gulp.src('./node_modules/jquery/dist/jquery.min.js'),
        gulp.src('./node_modules/materialize-css/dist/js/materialize.js'),
        gulp.src('./node_modules/codemirror/lib/codemirror.js'),
        gulp.src('./node_modules/codemirror/mode/clike/clike.js'),
        gulp.src('./node_modules/codemirror/addon/edit/matchbrackets.js'),
        gulp.src('./node_modules/codemirror/mode/xml/xml.js'),
        gulp.src('./node_modules/codemirror/mode/javascript/javascript.js'),
        gulp.src('./node_modules/codemirror/mode/htmlmixed/htmlmixed.js'),
        gulp.src('./node_modules/codemirror/mode/css/css.js'),
        gulp.src('./node_modules/codemirror/mode/php/php.js'),
        gulp.src('./static/app.js')
    )
    .pipe(concat('scripts.js'))
    .pipe(uglify())
    .pipe(gulp.dest('./static'));
});

// Concatenate and minify stylesheets
gulp.task('styles', function() {
    return streamqueue({ objectMode: true },
        gulp.src('./node_modules/materialize-css/dist/css/materialize.css'),
        gulp.src('./static/app.css'),
        //gulp.src('./node_modules/codemirror/lib/codemirror.css'),
    )
    .pipe(concat('styles.css'))
    .pipe(cssPurge({
        trim : true,
        shorten : true,
        verbose : false
    }))
    .pipe(gulp.dest('./static'));
});

// Copy file that is needed as-is
gulp.task('copy', function () {
    return gulp.src('./node_modules/codemirror/lib/codemirror.css')
        .pipe(gulp.dest('static'));
});

// Run entire process
gulp.task('default', gulp.series('scripts', 'styles', 'copy'));