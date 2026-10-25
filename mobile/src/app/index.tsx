import { Redirect } from 'expo-router';
import { SafeAreaView } from 'react-native-safe-area-context';
import { LoginScreen } from '../features/auth/LoginScreen';
import { useAuth } from '../context/AuthContext';
import { LoadingState } from '../components/LoadingState';
import { colors } from '../theme/colors';
import { StyleSheet } from 'react-native';

export default function IndexRoute() {
  const { isLoading, session } = useAuth();

  if (isLoading) {
    return (
      <SafeAreaView style={styles.loading}>
        <LoadingState message="Verificando sua sessão..." />
      </SafeAreaView>
    );
  }
  if (session) {
    return <Redirect href="/app" />;
  }
  return <LoginScreen />;
}

const styles = StyleSheet.create({
  loading: {
    backgroundColor: colors.background,
    flex: 1,
  },
});
