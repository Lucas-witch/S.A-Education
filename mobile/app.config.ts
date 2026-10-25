import type { ExpoConfig } from 'expo/config';
import { validateProductionApiUrl } from './src/utils/productionApiUrl.js';

const isDevelopment = (process.env.APP_ENV ?? 'development') === 'development';
const isProduction = process.env.APP_ENV === 'production';
const allowDevelopmentHttp =
  isDevelopment && process.env.EXPO_PUBLIC_ALLOW_HTTP_API === 'true';

if (isProduction) {
  validateProductionApiUrl(process.env.EXPO_PUBLIC_API_BASE_URL);
}

const config: ExpoConfig = {
  name: 'S.A Education',
  slug: 'sa-education-mobile',
  scheme: 'saeducation',
  version: '1.0.0',
  orientation: 'default',
  userInterfaceStyle: 'light',
  icon: './assets/images/app-logo.png',
  android: {
    package: 'com.saeducation.mobile',
    adaptiveIcon: {
      foregroundImage: './assets/images/app-logo.png',
      backgroundColor: '#4C2365',
    },
  },
  plugins: [
    'expo-router',
    'expo-font',
    ['expo-secure-store', { configureAndroidBackup: true }],
    [
      'expo-splash-screen',
      {
        image: './assets/images/app-logo.png',
        imageWidth: 180,
        resizeMode: 'contain',
        backgroundColor: '#F7F2EB',
      },
    ],
    ['./plugins/with-development-cleartext', { enabled: allowDevelopmentHttp }],
  ],
  experiments: {
    typedRoutes: true,
  },
};

export default config;
