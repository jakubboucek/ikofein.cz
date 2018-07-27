module.exports = function(grunt) {

  // Project configuration.
  grunt.initConfig({
    pkg: grunt.file.readJSON('package.json'),
    uglify: {
      options: {
        banner: '/*! Package: <%= pkg.name %> <%= pkg.version %> (build: <%= grunt.template.today("yyyy-mm-dd") %>), author: <%= pkg.author %> */\n',
      },
      default: {
        files: {
          'www/js/main.js': [
              'asset/js/libs/jquery-2.1.1.js',
              'asset/js/core.js',
              'asset/js/libs/slimbox-2.05.js',
              'asset/js/gallery.js',
              'asset/js/libs/slimbox-autoload.js'
            ]
        }
      }
    },
    less: {
      options: {
        banner: '/*! Package: <%= pkg.name %> <%= pkg.version %> (build: <%= grunt.template.today("yyyy-mm-dd") %>), author: <%= pkg.author %> */\n',
        compress: true
      },
      default: {
        files: {
          'www/css/main.css': [
              'asset/less/style.less',
              'asset/less/alert.less',
              'asset/less/slimbox2.less'
          ],
          'www/css/admin.css': [
              'asset/less/admin.less'
          ]
        }
      }
    }
  });

  // Load the plugin that provides the "uglify" task.
  grunt.loadNpmTasks('grunt-contrib-uglify');
  grunt.loadNpmTasks('grunt-contrib-less');

  // Default task(s).
  grunt.registerTask('default', ['uglify','less']);

};