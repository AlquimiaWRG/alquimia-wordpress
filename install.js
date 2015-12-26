"use strict";

module.exports = function(done) {
  var fs = require('fs');
  var appDir = alquimia.getPath('appDir');
  var scriptsDir = alquimia.getPath('scriptsDir');

  fs.mkdirSync('wp-alquimia');
  alquimia.copy(__dirname + '/assets/wp-alquimia', 'wp-alquimia');

  fs.mkdirSync('wp-sample');
  alquimia.copy(__dirname + '/assets/wp-sample', 'wp-sample');

  print('\nDownloading Wordpress...\n');

  alquimia.Downloader.download('https://wordpress.org/latest.zip', function(buffer) {
    alquimia.Downloader.extract(buffer, '.', function() {
      fs.renameSync('wordpress', 'admin');

      clear();
      print('Moving Wordpress plugins...\n');

      var wordpressPath = 'admin/wp-content';
      var alquimiaPath = wordpressPath + '/plugins/wp-alquimia';
      var pluginPath = wordpressPath + '/plugins/' + alquimia.config.appName.dashed;

      fs.mkdirSync(alquimiaPath);
      fs.mkdirSync(pluginPath);

      var copy = function copy(file) {
        var content = fs.readFileSync(file, 'utf8');

        for (var i in alquimia.nameSubstitutionMap) {
          content = content.replace(alquimia.nameSubstitutionMap[i], alquimia.config.appName[i]);
        }

        fs.writeFileSync(file, content, 'utf8');
      };

      alquimia.copy('wp-alquimia', alquimiaPath, copy);
      alquimia.copy('wp-sample', pluginPath, copy);

      fs.renameSync(pluginPath + '/sample.php', pluginPath + '/' + alquimia.config.appName.dashed + '.php');
      fs.renameSync(pluginPath + '/classes/class-sample.php',
        pluginPath + '/classes/class-' + alquimia.config.appName.dashed + '.php');

      alquimia.del('wp-alquimia');
      alquimia.del('wp-sample');

      fs.mkdirSync(appDir + '/' + scriptsDir + '/wordpress');
      alquimia.copy(__dirname + '/assets/wordpress', appDir + '/' + scriptsDir + '/wordpress');

      alquimia.config.SERVER = 'http://localhost/' + alquimia.config.appName.dashed + '/admin/';

      done();
    });
  });
};
