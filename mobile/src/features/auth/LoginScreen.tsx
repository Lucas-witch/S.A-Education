import { Ionicons } from '@expo/vector-icons';
import { useState } from 'react';
import {
  KeyboardAvoidingView,
  Platform,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { AppButton } from '../../components/AppButton';
import { AppCard } from '../../components/AppCard';
import { AppTextField } from '../../components/AppTextField';
import { InlineMessage } from '../../components/InlineMessage';
import { useAuth } from '../../context/AuthContext';
import { ApiError } from '../../services/api/client';
import { colors, radii, spacing } from '../../theme/colors';
import type { AccountType } from '../../types/api';
import { AppHeader } from '../../components/AppHeader';
import { validateLoginInput } from './validation';

export function LoginScreen() {
  const { initializationError, isSubmitting, retryRestore, signIn } = useAuth();
  const [identifier, setIdentifier] = useState('');
  const [password, setPassword] = useState('');
  const [accountType, setAccountType] = useState<AccountType>('user');
  const [error, setError] = useState<string | null>(null);
  const [isRetrying, setIsRetrying] = useState(false);

  async function handleLogin() {
    setError(null);
    const validationError = validateLoginInput(identifier, password);
    if (validationError) {
      setError(validationError);
      return;
    }
    try {
      await signIn({
        identifier: identifier.trim(),
        password,
        account_type: accountType,
        device_name: Platform.OS === 'android' ? 'Android' : 'iOS',
      });
    } catch (cause) {
      setError(
        cause instanceof ApiError
          ? cause.message
          : 'Não foi possível iniciar a sessão. Tente novamente.',
      );
    }
  }

  async function handleRetry() {
    setIsRetrying(true);
    setError(null);
    try {
      await retryRestore();
    } finally {
      setIsRetrying(false);
    }
  }

  return (
    <SafeAreaView edges={['top', 'bottom']} style={styles.safeArea}>
      <KeyboardAvoidingView
        behavior={Platform.OS === 'ios' ? 'padding' : undefined}
        style={styles.flex}
      >
        <ScrollView
          contentContainerStyle={styles.content}
          keyboardShouldPersistTaps="handled"
        >
          <View style={styles.brand}>
            <AppHeader
              title="S.A Education"
              subtitle="Aprender conecta. Entre para continuar."
            />
          </View>

          <AppCard style={styles.formCard}>
            <Text style={styles.formTitle}>Acesse sua conta</Text>
            <Text style={styles.formSubtitle}>
              Use o mesmo usuário ou e-mail e senha da plataforma.
            </Text>

            <View style={styles.accountOptions}>
              <AccountTypeOption
                icon="person-outline"
                label="Estudante ou professor"
                selected={accountType === 'user'}
                onPress={() => setAccountType('user')}
              />
              <AccountTypeOption
                icon="school-outline"
                label="Instituição"
                selected={accountType === 'institution'}
                onPress={() => setAccountType('institution')}
              />
            </View>

            <AppTextField
              autoCapitalize="none"
              autoComplete="username"
              autoCorrect={false}
              label="Usuário ou e-mail"
              maxLength={255}
              onChangeText={setIdentifier}
              placeholder="Seu usuário ou e-mail"
              returnKeyType="next"
              textContentType="username"
              value={identifier}
            />
            <AppTextField
              autoCapitalize="none"
              autoComplete="password"
              label="Senha"
              maxLength={4096}
              onChangeText={setPassword}
              placeholder="Sua senha"
              returnKeyType="go"
              secureTextEntry
              textContentType="password"
              value={password}
            />

            {initializationError ? (
              <View style={styles.messageGroup}>
                <InlineMessage message={initializationError} />
                <AppButton
                  loading={isRetrying}
                  onPress={() => void handleRetry()}
                  title="Tentar reconectar"
                  variant="outline"
                />
              </View>
            ) : null}
            {error ? <InlineMessage message={error} /> : null}

            <AppButton
              disabled={!identifier.trim() || password.length === 0}
              loading={isSubmitting}
              onPress={() => void handleLogin()}
              title="Entrar"
            />
            <Text style={styles.accountNote}>
              Cadastro e recuperação de senha ainda são feitos pela versão web.
            </Text>
          </AppCard>
        </ScrollView>
      </KeyboardAvoidingView>
    </SafeAreaView>
  );
}

interface AccountTypeOptionProps {
  icon: React.ComponentProps<typeof Ionicons>['name'];
  label: string;
  selected: boolean;
  onPress: () => void;
}

function AccountTypeOption({ icon, label, selected, onPress }: AccountTypeOptionProps) {
  return (
    <Pressable
      accessibilityRole="radio"
      accessibilityState={{ selected }}
      onPress={onPress}
      style={[styles.accountOption, selected && styles.accountOptionSelected]}
    >
      <Ionicons color={selected ? colors.purple : colors.muted} name={icon} size={19} />
      <Text style={[styles.accountLabel, selected && styles.accountLabelSelected]}>
        {label}
      </Text>
    </Pressable>
  );
}

const styles = StyleSheet.create({
  safeArea: {
    backgroundColor: colors.background,
    flex: 1,
  },
  flex: {
    flex: 1,
  },
  content: {
    alignSelf: 'center',
    flexGrow: 1,
    justifyContent: 'center',
    maxWidth: 520,
    paddingHorizontal: spacing.lg,
    paddingVertical: spacing.xl,
    width: '100%',
  },
  brand: {
    marginBottom: spacing.xl,
    paddingHorizontal: spacing.xs,
  },
  formCard: {
    gap: spacing.lg,
  },
  formTitle: {
    color: colors.text,
    fontSize: 22,
    fontWeight: '800',
  },
  formSubtitle: {
    color: colors.muted,
    fontSize: 14,
    marginTop: -spacing.md,
  },
  accountOptions: {
    gap: spacing.sm,
  },
  accountOption: {
    alignItems: 'center',
    backgroundColor: colors.surfaceSoft,
    borderColor: colors.border,
    borderRadius: radii.sm,
    borderWidth: 1,
    flexDirection: 'row',
    gap: spacing.sm,
    minHeight: 48,
    paddingHorizontal: spacing.md,
  },
  accountOptionSelected: {
    backgroundColor: colors.purpleSoft,
    borderColor: colors.purple,
  },
  accountLabel: {
    color: colors.muted,
    fontSize: 14,
    fontWeight: '600',
  },
  accountLabelSelected: {
    color: colors.purple,
  },
  messageGroup: {
    gap: spacing.sm,
  },
  accountNote: {
    color: colors.muted,
    fontSize: 12,
    textAlign: 'center',
  },
});
