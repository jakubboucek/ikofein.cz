module.exports = function (grunt) {

    // Project configuration.
    grunt.initConfig({
        pkg: grunt.file.readJSON('package.json'),
        uglify: {
            options: {
                banner: '/*! <%= pkg.name %> <%= grunt.template.today("yyyy-mm-dd HH:MM:ss") %>\n *  Copyright (c) <%= grunt.template.today("yyyy") %> <%= pkg.author.name %> (<%= pkg.author.url %>) */\n',
                compress: true,
                beautify: false,
            },
            default: {
                files: {
                    'www/js/main.js': [
                        'bower_components/jquery/dist/jquery.js',
                        'bower_components/magnific-popup/dist/jquery.magnific-popup.js',
                        'asset/js/core.js',
                        'asset/js/gallery.js',
                    ]
                }
            }
        },
        less: {
            options: {
                banner: '/*! Package: <%= pkg.name %> <%= pkg.version %> (build: <%= grunt.template.today("yyyy-mm-dd") %>), author: <%= pkg.author %> */\n',
                compress: true,
            },
            default: {
                files: {
                    'www/css/main.css': [
                        'asset/less/style.less',
                        'asset/less/alert.less',
                        'bower_components/magnific-popup/dist/magnific-popup.css',
                        'asset/less/gallery.less',
                    ],
                    'www/css/admin.css': [
                        'asset/less/admin.less',
                    ],
                }
            }
        }
    });

    // Load the plugin that provides the "uglify" task.
    grunt.loadNpmTasks('grunt-contrib-uglify-es');
    grunt.loadNpmTasks('grunt-contrib-less');

    // Default task(s).
    grunt.registerTask('default', ['uglify', 'less']);

};