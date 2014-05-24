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
      build: {
        src: 'dashboard/index.html',
        dest: '.public/index.html'
      }
    },
    clean: ['.work', '.public']
  });

  grunt.loadNpmTasks('grunt-bower-concat');
  grunt.loadNpmTasks('grunt-contrib-concat');
  grunt.loadNpmTasks('grunt-contrib-clean');
  grunt.loadNpmTasks('grunt-contrib-copy');

  grunt.registerTask('build', ['clean', 'copy:build', 'bower_concat', 'concat']);

};
