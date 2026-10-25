import { Stack } from 'expo-router';
import { StatusBar } from 'react-native';
import { AuthProvider } from '../context/AuthContext';
import { colors } from '../theme/colors';

export default function RootLayout() {
  return (
    <AuthProvider>
      <StatusBar backgroundColor={colors.background} barStyle="dark-content" />
      <Stack
        screenOptions={{
          animation: 'fade',
          contentStyle: { backgroundColor: colors.background },
          headerShown: false,
        }}
      />
    </AuthProvider>
  );
}
