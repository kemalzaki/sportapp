import { CapacitorConfig } from '@capacitor/cli';

const config: CapacitorConfig = {
  appId: 'com.yukmari.kawankeringatappsport',
  appName: 'KawanKeringat AppSport',
  webDir: 'www',

  server: {
    url: 'https://hapfam.alwaysdata.net/',
    cleartext: true
  }
};

export default config;