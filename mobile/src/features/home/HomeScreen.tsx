import { Ionicons } from '@expo/vector-icons';
import { useRouter } from 'expo-router';
import { Alert, Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { AppButton } from '../../components/AppButton';
import { AppCard } from '../../components/AppCard';
import { AppHeader } from '../../components/AppHeader';
import { useAuth } from '../../context/AuthContext';
import { colors, radii, spacing } from '../../theme/colors';
import type { AccountType } from '../../types/api';

const profileLabels: Record<AccountType, string> = {
  user: 'Conta de estudante ou professor',
  institution: 'Conta institucional',
};

export function HomeScreen() {
  const { session, signOut } = useAuth();
  const router = useRouter();
  if (!session) {
    return null;
  }

  const firstName = session.account.nome_completo.trim().split(/\s+/)[0];

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
    <SafeAreaView edges={['top', 'bottom']} style={styles.safeArea}>
      <ScrollView contentContainerStyle={styles.content}>
        <AppHeader
          subtitle="Sua jornada de aprendizagem começa aqui."
          title={`Olá, ${firstName}`}
        />

        <AppCard style={styles.accountCard}>
          <View style={styles.accountHeading}>
            <View style={styles.avatar}>
              <Ionicons color={colors.purple} name="person-outline" size={22} />
            </View>
            <View style={styles.accountCopy}>
              <Text style={styles.accountName}>{session.account.nome_completo}</Text>
              <Text style={styles.accountType}>{profileLabels[session.accountType]}</Text>
            </View>
          </View>
          <View style={styles.divider} />
          <Text style={styles.label}>E-mail</Text>
          <Text style={styles.email}>{session.account.email}</Text>
        </AppCard>

        <AppCard style={styles.featuresCard}>
          <View style={styles.sectionHeading}>
            <View style={styles.statusDot} />
            <Text style={styles.sectionTitle}>Acesse suas áreas</Text>
          </View>
          <Text style={styles.apiNote}>
            Salas e conteúdos da Biblioteca são carregados da plataforma. Recursos sem
            operação mobile disponível são identificados nas respectivas telas.
          </Text>
          <View style={styles.shortcutList}>
            <Shortcut
              icon="people-outline"
              label="Explorar salas"
              onPress={() => router.push('/app/rooms')}
            />
            <Shortcut
              icon="book-outline"
              label="Pesquisar na Biblioteca"
              onPress={() => router.push('/app/library')}
            />
            <Shortcut
              icon="trophy-outline"
              label="Ver ranking público"
              onPress={() => router.push('/app/ranking')}
            />
          </View>
          <View style={styles.divider} />
          <Text style={styles.pendingTitle}>Em integração</Text>
          <Text style={styles.apiNote}>
            Feed, questões, leitura detalhada, cadastro, recuperação de senha e portal
            institucional ainda dependem de operações mobile ou de implementação da
            interface.
          </Text>
          <View style={styles.accountHint}>
            <Ionicons
              color={colors.greenDeep}
              name="shield-checkmark-outline"
              size={18}
            />
            <Text style={styles.accountHintText}>
              Suas permissões são verificadas no servidor para cada operação.
            </Text>
          </View>
        </AppCard>

        <AppButton
          onPress={() => void handleSignOut()}
          title="Sair da conta"
          variant="outline"
        />
      </ScrollView>
    </SafeAreaView>
  );
}

function Shortcut({
  icon,
  label,
  onPress,
}: {
  icon: React.ComponentProps<typeof Ionicons>['name'];
  label: string;
  onPress: () => void;
}) {
  return (
    <Pressable
      accessibilityRole="button"
      onPress={onPress}
      style={({ pressed }) => [styles.shortcut, pressed && styles.shortcutPressed]}
    >
      <Ionicons color={colors.purple} name={icon} size={20} />
      <Text style={styles.shortcutLabel}>{label}</Text>
      <Ionicons color={colors.muted} name="chevron-forward" size={18} />
    </Pressable>
  );
}

const styles = StyleSheet.create({
  safeArea: {
    backgroundColor: colors.background,
    flex: 1,
  },
  content: {
    alignSelf: 'center',
    gap: spacing.lg,
    maxWidth: 720,
    padding: spacing.lg,
    paddingBottom: spacing.xl,
    width: '100%',
  },
  accountCard: {
    gap: spacing.md,
  },
  accountHeading: {
    alignItems: 'center',
    flexDirection: 'row',
    gap: spacing.md,
  },
  avatar: {
    alignItems: 'center',
    backgroundColor: colors.greenSoft,
    borderRadius: radii.pill,
    height: 48,
    justifyContent: 'center',
    width: 48,
  },
  accountCopy: {
    flex: 1,
    gap: spacing.xs,
  },
  accountName: {
    color: colors.text,
    fontSize: 16,
    fontWeight: '700',
  },
  accountType: {
    color: colors.muted,
    fontSize: 13,
  },
  divider: {
    backgroundColor: colors.border,
    height: StyleSheet.hairlineWidth,
  },
  label: {
    color: colors.muted,
    fontSize: 12,
    fontWeight: '700',
    marginBottom: -spacing.sm,
  },
  email: {
    color: colors.text,
    fontSize: 14,
  },
  featuresCard: {
    gap: spacing.md,
  },
  sectionHeading: {
    alignItems: 'center',
    flexDirection: 'row',
    gap: spacing.sm,
  },
  statusDot: {
    backgroundColor: colors.green,
    borderRadius: radii.pill,
    height: 9,
    width: 9,
  },
  sectionTitle: {
    color: colors.text,
    fontSize: 17,
    fontWeight: '700',
  },
  apiNote: {
    color: colors.muted,
    fontSize: 12,
    textAlign: 'center',
  },
  shortcutList: {
    gap: spacing.xs,
  },
  shortcut: {
    alignItems: 'center',
    borderRadius: radii.sm,
    flexDirection: 'row',
    gap: spacing.md,
    minHeight: 48,
    paddingHorizontal: spacing.sm,
  },
  shortcutPressed: {
    backgroundColor: colors.purpleSoft,
  },
  shortcutLabel: {
    color: colors.text,
    flex: 1,
    fontSize: 14,
    fontWeight: '600',
  },
  pendingTitle: {
    color: colors.text,
    fontSize: 14,
    fontWeight: '700',
    textAlign: 'center',
  },
  accountHint: {
    alignItems: 'center',
    backgroundColor: colors.greenSoft,
    borderRadius: radii.sm,
    flexDirection: 'row',
    gap: spacing.sm,
    padding: spacing.md,
  },
  accountHintText: {
    color: colors.greenDeep,
    flex: 1,
    fontSize: 12,
  },
});
