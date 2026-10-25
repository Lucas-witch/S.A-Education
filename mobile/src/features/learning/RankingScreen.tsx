import { Ionicons } from '@expo/vector-icons';
import { useCallback, useEffect, useState } from 'react';
import { ScrollView, StyleSheet, Text, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { AppButton } from '../../components/AppButton';
import { AppCard } from '../../components/AppCard';
import { AppHeader } from '../../components/AppHeader';
import { InlineMessage } from '../../components/InlineMessage';
import { LoadingState } from '../../components/LoadingState';
import { useAuth } from '../../context/AuthContext';
import { colors, radii, spacing } from '../../theme/colors';
import { parseRanking, type RankingEntry } from './api';

export function RankingScreen() {
  const { request } = useAuth();
  const [entries, setEntries] = useState<RankingEntry[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const loadRanking = useCallback(async () => {
    setIsLoading(true);
    setError(null);
    try {
      const result = await request<unknown>('index.php?resource=ranking');
      setEntries(parseRanking(result.data));
    } catch (cause) {
      setError(
        cause instanceof Error ? cause.message : 'Não foi possível carregar o ranking.',
      );
    } finally {
      setIsLoading(false);
    }
  }, [request]);

  useEffect(() => {
    void loadRanking();
  }, [loadRanking]);

  return (
    <SafeAreaView edges={['top']} style={styles.safeArea}>
      <ScrollView contentContainerStyle={styles.content}>
        <AppHeader title="Ranking" subtitle="Participantes que escolheram aparecer." />
        {error ? (
          <View style={styles.feedback}>
            <InlineMessage message={error} />
            <AppButton
              onPress={() => void loadRanking()}
              title="Tentar novamente"
              variant="outline"
            />
          </View>
        ) : isLoading ? (
          <LoadingState message="Carregando ranking..." />
        ) : entries.length === 0 ? (
          <AppCard>
            <Text style={styles.emptyTitle}>Ranking indisponível</Text>
            <Text style={styles.muted}>
              Não há participantes públicos para exibir neste momento.
            </Text>
          </AppCard>
        ) : (
          <AppCard style={styles.list}>
            {entries.map((entry, index) => (
              <View key={`${entry.posicao}-${entry.nome_publico}`} style={styles.entry}>
                <View style={[styles.position, index < 3 && styles.topPosition]}>
                  {index < 3 ? (
                    <Ionicons color={colors.purple} name="ribbon-outline" size={18} />
                  ) : (
                    <Text style={styles.positionText}>{entry.posicao}</Text>
                  )}
                </View>
                <Text style={styles.name}>{entry.nome_publico}</Text>
                <Text style={styles.points}>{entry.pontos} pts</Text>
              </View>
            ))}
          </AppCard>
        )}
        <Text style={styles.note}>
          Exibição limitada aos dados públicos permitidos pelo consentimento e pelas
          regras do ranking no servidor.
        </Text>
      </ScrollView>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  safeArea: { backgroundColor: colors.background, flex: 1 },
  content: {
    alignSelf: 'center',
    gap: spacing.md,
    maxWidth: 720,
    padding: spacing.lg,
    paddingBottom: spacing.xl,
    width: '100%',
  },
  feedback: { gap: spacing.sm },
  list: { gap: spacing.sm, paddingVertical: spacing.sm },
  entry: {
    alignItems: 'center',
    borderBottomColor: colors.border,
    borderBottomWidth: StyleSheet.hairlineWidth,
    flexDirection: 'row',
    gap: spacing.md,
    minHeight: 58,
    paddingVertical: spacing.sm,
  },
  position: {
    alignItems: 'center',
    backgroundColor: colors.surfaceSoft,
    borderRadius: radii.pill,
    height: 34,
    justifyContent: 'center',
    width: 34,
  },
  topPosition: { backgroundColor: colors.greenSoft },
  positionText: { color: colors.muted, fontSize: 13, fontWeight: '700' },
  name: { color: colors.text, flex: 1, fontSize: 14, fontWeight: '600' },
  points: { color: colors.purple, fontSize: 13, fontWeight: '700' },
  emptyTitle: {
    color: colors.text,
    fontSize: 16,
    fontWeight: '700',
    marginBottom: spacing.xs,
  },
  muted: { color: colors.muted, fontSize: 13 },
  note: { color: colors.muted, fontSize: 12, textAlign: 'center' },
});
