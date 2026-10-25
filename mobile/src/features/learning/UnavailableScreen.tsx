import { ScrollView, StyleSheet, Text } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { AppCard } from '../../components/AppCard';
import { AppHeader } from '../../components/AppHeader';
import { EmptyState } from '../../components/EmptyState';
import { colors, spacing } from '../../theme/colors';

interface UnavailableScreenProps {
  title: string;
  subtitle: string;
  explanation: string;
}

export function UnavailableScreen({
  title,
  subtitle,
  explanation,
}: UnavailableScreenProps) {
  return (
    <SafeAreaView edges={['top']} style={styles.safeArea}>
      <ScrollView contentContainerStyle={styles.content}>
        <AppHeader title={title} subtitle={subtitle} />
        <AppCard>
          <EmptyState description={explanation} title="Integração ainda não disponível" />
          <Text style={styles.note}>
            Esta tela não carrega conteúdo de exemplo nem confirma operações que o
            servidor não oferece.
          </Text>
        </AppCard>
      </ScrollView>
    </SafeAreaView>
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
  note: { color: colors.muted, fontSize: 12, textAlign: 'center' },
});
