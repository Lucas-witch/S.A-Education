import { Alert, ScrollView, StyleSheet, Text, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { AppButton } from '../../components/AppButton';
import { AppCard } from '../../components/AppCard';
import { AppHeader } from '../../components/AppHeader';
import { InlineMessage } from '../../components/InlineMessage';
import { useAuth } from '../../context/AuthContext';
import { colors, spacing } from '../../theme/colors';

export function ProfileScreen() {
  const { session, signOut } = useAuth();
  if (!session) {
    return null;
  }

  async function handleSignOut() {
    try {
      await signOut();
    } catch (error) {
      Alert.alert(
        'Sessão encerrada',
        error instanceof Error ? error.message : 'A sessão foi removida deste aparelho.',
      );
    }
  }

  return (
    <SafeAreaView edges={['top']} style={styles.safeArea}>
      <ScrollView contentContainerStyle={styles.content}>
        <AppHeader title="Perfil" subtitle="Dados da conta autenticada." />
        <AppCard style={styles.card}>
          <ProfileField label="Nome" value={session.account.nome_completo} />
          <ProfileField label="E-mail" value={session.account.email} />
          <ProfileField label="Usuário" value={session.account.username} />
          <ProfileField
            label="Tipo de conta"
            value={
              session.accountType === 'institution'
                ? 'Instituição'
                : session.account.perfil === 'professor'
                  ? 'Professor'
                  : 'Estudante'
            }
          />
        </AppCard>
        <InlineMessage
          message="A edição do perfil e das configurações ainda não foi conectada ao aplicativo. A autorização da conta institucional continua sendo definida pelo servidor."
          tone="info"
        />
        <AppButton
          onPress={() => void handleSignOut()}
          title="Sair da conta"
          variant="outline"
        />
      </ScrollView>
    </SafeAreaView>
  );
}

function ProfileField({ label, value }: { label: string; value: string }) {
  return (
    <View style={styles.field}>
      <Text style={styles.label}>{label}</Text>
      <Text selectable style={styles.value}>
        {value}
      </Text>
    </View>
  );
}

const styles = StyleSheet.create({
  safeArea: { backgroundColor: colors.background, flex: 1 },
  content: {
    alignSelf: 'center',
    gap: spacing.lg,
    maxWidth: 720,
    padding: spacing.lg,
    paddingBottom: spacing.xl,
    width: '100%',
  },
  card: { gap: spacing.md },
  field: { gap: spacing.xs },
  label: { color: colors.muted, fontSize: 12, fontWeight: '700' },
  value: { color: colors.text, fontSize: 15 },
});
