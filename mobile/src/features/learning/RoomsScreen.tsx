import { Ionicons } from '@expo/vector-icons';
import { useCallback, useEffect, useState } from 'react';
import { Pressable, ScrollView, StyleSheet, Text, TextInput, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { AppCard } from '../../components/AppCard';
import { AppHeader } from '../../components/AppHeader';
import { AppButton } from '../../components/AppButton';
import { InlineMessage } from '../../components/InlineMessage';
import { LoadingState } from '../../components/LoadingState';
import { useAuth } from '../../context/AuthContext';
import { colors, radii, spacing } from '../../theme/colors';
import { parseRooms, roomsSearchPath, type StudyRoom } from './api';

export function RoomsScreen() {
  const { request } = useAuth();
  const [query, setQuery] = useState('');
  const [submittedQuery, setSubmittedQuery] = useState('');
  const [rooms, setRooms] = useState<StudyRoom[]>([]);
  const [page, setPage] = useState(1);
  const [pages, setPages] = useState(1);
  const [total, setTotal] = useState(0);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const loadRooms = useCallback(async () => {
    setIsLoading(true);
    setError(null);
    try {
      const response = await request<unknown>(roomsSearchPath(submittedQuery, page));
      const nextRooms = parseRooms(response.data);
      const meta = response.meta;
      const nextPage =
        meta && typeof meta.page === 'number' ? Math.max(1, meta.page) : page;
      const nextPages =
        meta && typeof meta.pages === 'number' ? Math.max(1, meta.pages) : 1;
      const nextTotal =
        meta && typeof meta.total === 'number'
          ? Math.max(0, meta.total)
          : nextRooms.length;
      setRooms(nextRooms);
      setPage(nextPage);
      setPages(nextPages);
      setTotal(nextTotal);
    } catch (cause) {
      setError(
        cause instanceof Error ? cause.message : 'Não foi possível carregar as salas.',
      );
    } finally {
      setIsLoading(false);
    }
  }, [page, request, submittedQuery]);

  useEffect(() => {
    void loadRooms();
  }, [loadRooms]);

  function submitSearch() {
    setPage(1);
    setSubmittedQuery(query.trim());
  }

  function changePage(nextPage: number) {
    setPage(nextPage);
  }

  return (
    <SafeAreaView edges={['top']} style={styles.safeArea}>
      <ScrollView
        contentContainerStyle={styles.content}
        keyboardShouldPersistTaps="handled"
      >
        <AppHeader title="Salas de estudo" subtitle="Encontre espaços de aprendizagem." />
        <View style={styles.searchRow}>
          <TextInput
            accessibilityLabel="Pesquisar salas"
            autoCapitalize="none"
            maxLength={120}
            onChangeText={setQuery}
            onSubmitEditing={submitSearch}
            placeholder="Pesquisar por nome ou matéria"
            placeholderTextColor={colors.muted}
            returnKeyType="search"
            style={styles.searchInput}
            value={query}
          />
          <Pressable
            accessibilityLabel="Pesquisar"
            accessibilityRole="button"
            onPress={submitSearch}
            style={styles.searchButton}
          >
            <Ionicons color={colors.white} name="search" size={21} />
          </Pressable>
        </View>
        {error ? (
          <View style={styles.feedback}>
            <InlineMessage message={error} />
            <AppButton
              onPress={() => void loadRooms()}
              title="Tentar novamente"
              variant="outline"
            />
          </View>
        ) : isLoading ? (
          <LoadingState message="Carregando salas..." />
        ) : rooms.length === 0 ? (
          <AppCard>
            <Text style={styles.emptyTitle}>Nenhuma sala encontrada</Text>
            <Text style={styles.muted}>Tente outro termo de pesquisa.</Text>
          </AppCard>
        ) : (
          <>
            <Text style={styles.resultCount}>
              {total} {total === 1 ? 'sala disponível' : 'salas disponíveis'}
            </Text>
            {rooms.map((room) => (
              <AppCard key={room.id} style={styles.roomCard}>
                <View style={styles.titleRow}>
                  <View style={styles.roomIcon}>
                    <Ionicons color={colors.purple} name="people-outline" size={20} />
                  </View>
                  <View style={styles.roomTitleCopy}>
                    <Text style={styles.roomTitle}>{room.nome}</Text>
                    <Text style={styles.muted}>
                      {room.materia ||
                        room.area_conhecimento ||
                        room.categoria ||
                        'Sala de estudo'}
                    </Text>
                  </View>
                </View>
                {room.descricao ? (
                  <Text style={styles.description}>{room.descricao}</Text>
                ) : null}
                <View style={styles.details}>
                  <Text style={styles.detail}>
                    {room.privacidade === 'publica' ? 'Pública' : 'Acesso restrito'}
                  </Text>
                  {room.prof ? (
                    <Text style={styles.detail}>Responsável: {room.prof}</Text>
                  ) : null}
                  <Text style={styles.detail}>
                    {room.alunos} {room.alunos === 1 ? 'participante' : 'participantes'}
                  </Text>
                </View>
              </AppCard>
            ))}
            {pages > 1 ? (
              <View style={styles.pagination}>
                <AppButton
                  disabled={page <= 1}
                  onPress={() => changePage(page - 1)}
                  title="Anterior"
                  variant="outline"
                />
                <Text style={styles.pageLabel}>
                  {page} / {pages}
                </Text>
                <AppButton
                  disabled={page >= pages}
                  onPress={() => changePage(page + 1)}
                  title="Próxima"
                  variant="outline"
                />
              </View>
            ) : null}
          </>
        )}
        <Text style={styles.note}>
          A listagem respeita o acesso definido pelo servidor. Criar, entrar e gerenciar
          salas ainda não estão disponíveis nesta API mobile.
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
  searchRow: { alignItems: 'center', flexDirection: 'row', gap: spacing.sm },
  searchInput: {
    backgroundColor: colors.surface,
    borderColor: colors.border,
    borderRadius: radii.md,
    borderWidth: 1,
    color: colors.text,
    flex: 1,
    fontSize: 15,
    minHeight: 52,
    paddingHorizontal: spacing.md,
  },
  searchButton: {
    alignItems: 'center',
    backgroundColor: colors.purple,
    borderRadius: radii.md,
    height: 52,
    justifyContent: 'center',
    width: 52,
  },
  feedback: { gap: spacing.sm },
  resultCount: { color: colors.muted, fontSize: 13, fontWeight: '600' },
  roomCard: { gap: spacing.md },
  titleRow: { alignItems: 'center', flexDirection: 'row', gap: spacing.md },
  roomIcon: {
    alignItems: 'center',
    backgroundColor: colors.purpleSoft,
    borderRadius: radii.pill,
    height: 42,
    justifyContent: 'center',
    width: 42,
  },
  roomTitleCopy: { flex: 1, gap: spacing.xs },
  roomTitle: { color: colors.text, fontSize: 16, fontWeight: '700' },
  muted: { color: colors.muted, fontSize: 13 },
  description: { color: colors.text, fontSize: 14 },
  details: {
    borderTopColor: colors.border,
    borderTopWidth: StyleSheet.hairlineWidth,
    gap: spacing.xs,
    paddingTop: spacing.sm,
  },
  detail: { color: colors.muted, fontSize: 12 },
  pagination: {
    alignItems: 'center',
    flexDirection: 'row',
    gap: spacing.sm,
    justifyContent: 'space-between',
  },
  pageLabel: { color: colors.text, fontSize: 13, fontWeight: '600' },
  emptyTitle: {
    color: colors.text,
    fontSize: 16,
    fontWeight: '700',
    marginBottom: spacing.xs,
  },
  note: { color: colors.muted, fontSize: 12, textAlign: 'center' },
});
