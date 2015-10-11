var gulp = require('gulp');
var sass = require('gulp-sass');
var livereload = require('gulp-livereload');
var concat = require('gulp-concat');
var uglify = require('gulp-uglify');
var uglifycss = require('gulp-uglifycss');

gulp.task('sass', function () {
    gulp.src('./web/assets/resources/sass/app.scss')
        .pipe(sass({sourceComments: 'map'}))
        .pipe(gulp.dest('./web/assets/compile/'))
        .pipe(uglifycss({
            'max-line-len': 80
        }))
        .pipe(gulp.dest('./web/assets/compile-min/'));
});

gulp.task('js', function() {
    gulp.src([
        './web/components/jquery/dist/jquery.js',
        './web/components/bootstrap-sass/assets/javascripts/bootstrap.js',
        './web/assets/js/**/*.js',
    ])
        .pipe(concat('all.js'))
        .pipe(gulp.dest('./web/assets/compile/'))
        .pipe(uglify())
        .pipe(gulp.dest('./web/assets/compile-min/'));
});

gulp.task('watch', function () {
    livereload({start: true});

    var onChange = function (event) {
        console.log('File '+event.path+' has been '+event.type);
        livereload.reload();
    };
    gulp.watch('web/assets/resources/sass/**/*.scss', ['sass']);
    gulp.watch('web/assets/js/**/*js', ['js']);

    gulp.watch('web/assets/compile/*.css', onChange);
    gulp.watch('web/assets/compile/*.js', onChange);
    gulp.watch('app/Resources/**/*.twig', onChange);
    gulp.watch('src/**/*.php', onChange);
});

gulp.task('default', ['watch']);