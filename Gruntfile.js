module.exports = function(grunt) {

  grunt.initConfig({
    bower_concat: {
      dist: {
        dest: '.work/bower_components.js'
      }
    },
    concat: {
      dist: {
        src: ['.work/*.js', 'dashboard/js/*.js'],
        dest: '.public/js/recruiter.js'
      }
    },
    copy: {
      html: {
        src: 'dashboard/index.html',
        dest: '.public/index.html'
      },
      css: {
        src: 'bower_components/bootstrap/dist/css/bootstrap.css',
        dest: '.public/css/bootstrap.css'
      },
      fonts: {
        src: 'bower_components/bootstrap/dist/fonts/glyphicons-halflings-regular.ttf',
        dest: '.public/fonts/glyphicons-halflings-regular.ttf'
      }
    },
    clean: ['.work', '.public']
  });

  grunt.loadNpmTasks('grunt-bower-concat');
  grunt.loadNpmTasks('grunt-contrib-concat');
  grunt.loadNpmTasks('grunt-contrib-clean');
  grunt.loadNpmTasks('grunt-contrib-copy');

  grunt.registerTask('build', ['clean', 'copy:html', 'copy:css', 'copy:fonts', 'bower_concat', 'concat']);

};
