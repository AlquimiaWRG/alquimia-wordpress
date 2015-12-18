"use strict";

module.exports = function(done) {
  var fs = require('fs');

  fs.mkdirSync('wp-alquimia');
  alquimia.copy(__dirname + '/assets/wp-alquimia', 'wp-alquimia');

  fs.mkdirSync('wp-sample');
  alquimia.copy(__dirname + '/assets/wp-sample', 'wp-sample');

  fs.mkdirSync('wp-void');
  alquimia.copy(__dirname + '/assets/wp-void', 'wp-void');

  print('\nDownloading Wordpress...\n');

  alquimia.Downloader.download('https://wordpress.org/latest.zip', function(buffer) {
    alquimia.Downloader.extract(buffer, '.', function() {
      fs.renameSync('wordpress', 'admin');

      clear();
      print('Moving Wordpress plugins and themes...\n');

      var wordpressPath = 'admin/wp-content';
      var alquimiaPath = wordpressPath + '/plugins/wp-alquimia';
      var pluginPath = wordpressPath + '/plugins/' + alquimia.config.appName.dashed;
      var voidPath = wordpressPath + '/themes/void';

      fs.mkdirSync(alquimiaPath);
      fs.mkdirSync(pluginPath);
      fs.mkdirSync(voidPath);

      var copy = function copy(file) {
        var content = fs.readFileSync(file, 'utf8');

        for (var i in alquimia.nameSubstitutionMap) {
          content = content.replace(alquimia.nameSubstitutionMap[i], alquimia.config.appName[i]);
        }

        fs.writeFileSync(file, content, 'utf8');
      };

      alquimia.copy('wp-alquimia', alquimiaPath, copy);
      alquimia.copy('wp-sample', pluginPath, copy);
      alquimia.copy('wp-void', voidPath, copy);

      fs.renameSync(pluginPath + '/sample.php', pluginPath + '/' + alquimia.config.appName.dashed + '.php');
      fs.renameSync(pluginPath + '/classes/class-sample.php',
        pluginPath + '/classes/class-' + alquimia.config.appName.dashed + '.php');

      alquimia.del('wp-alquimia');
      alquimia.del('wp-sample');
      alquimia.del('wp-void');

      fs.mkdirSync('app/src/wordpress');
      alquimia.copy(__dirname + '/assets/wordpress', 'app/src/wordpress');

      done();
    });
  });
};
