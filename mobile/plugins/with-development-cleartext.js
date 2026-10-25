const { withAndroidManifest } = require('expo/config-plugins');

module.exports = function withDevelopmentCleartext(config, { enabled }) {
  return withAndroidManifest(config, (mod) => {
    const application = mod.modResults.manifest.application?.[0];
    if (!application) {
      throw new Error('Android manifest is missing its application element.');
    }

    application.$['android:usesCleartextTraffic'] = enabled ? 'true' : 'false';
    return mod;
  });
};
