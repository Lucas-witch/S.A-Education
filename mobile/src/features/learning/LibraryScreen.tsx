import { Ionicons } from '@expo/vector-icons';
import { useCallback, useEffect, useState } from 'react';
import { Pressable, ScrollView, StyleSheet, Text, TextInput, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { AppButton } from '../../components/AppButton';
import { AppCard } from '../../components/AppCard';
import { AppHeader } from '../../components/AppHeader';
import { InlineMessage } from '../../components/InlineMessage';
import { LoadingState } from '../../components/LoadingState';
import { useAuth } from '../../context/AuthContext';
import { colors, radii, spacing } from '../../theme/colors';
import { catalogSearchPath, parseCatalogPage, type CatalogItem } from './api';

const filters = [
  { label: 'Todos', value: '' },
  { label: 'Livros', value: 'livro' },
  { label: 'PDFs', value: 'pdf' },
  { label: 'Artigos', value: 'artigo' },
  { label: 'Apostilas', value: 'apostila' },
] as const;

export function LibraryScreen() {
  const { request } = useAuth();
  const [query, setQuery] = useState('');
  const [submittedQuery, setSubmittedQuery] = useState('');
  const [filter, setFilter] = useState('');
  const [items, setItems] = useState<CatalogItem[]>([]);
  const [page, setPage] = useState(1);
  const [pages, setPages] = useState(1);
  const [total, setTotal] = useState(0);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const loadCatalog = useCallback(async () => {
    setIsLoading(true);
    setError(null);
    try {
      const response = await request<unknown>(
        catalogSearchPath(submittedQuery, page, filter),
      );
      const result = parseCatalogPage(response.data, page);
      setItems(result.items);
      setPage(result.page);
      setPages(Math.max(1, result.totalPages));
      setTotal(result.total);
    } catch (cause) {
      setError(
        cause instanceof Error
          ? cause.message
          : 'Não foi possível carregar a Biblioteca.',
      );
    } finally {
      setIsLoading(false);
    }
  }, [filter, page, request, submittedQuery]);

  useEffect(() => {
    void loadCatalog();
  }, [loadCatalog]);

  function submitSearch() {
    setPage(1);
    setSubmittedQuery(query.trim());
  }

  function selectFilter(value: string) {
    setPage(1);
    setFilter(value);
  }

  return (
    <SafeAreaView edges={['top']} style={styles.safeArea}>
      <ScrollView
        contentContainerStyle={styles.content}
        keyboardShouldPersistTaps="handled"
      >
        <AppHeader
          title="Biblioteca"
          subtitle="Pesquise materiais e conteúdos disponíveis."
        />
        <View style={styles.searchRow}>
          <TextInput
            accessibilityLabel="Pesquisar na Biblioteca"
            autoCapitalize="none"
            maxLength={120}
            onChangeText={setQuery}
            onSubmitEditing={submitSearch}
            placeholder="Título, autor ou assunto"
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
        <ScrollView
          horizontal
          contentContainerStyle={styles.filterRow}
          showsHorizontalScrollIndicator={false}
        >
          {filters.map((item) => {
            const selected = filter === item.value;
            return (
              <Pressable
                accessibilityRole="button"
                accessibilityState={{ selected }}
                key={item.value || 'all'}
                onPress={() => selectFilter(item.value)}
                style={[styles.filter, selected && styles.filterSelected]}
              >
                <Text
                  style={[styles.filterLabel, selected && styles.filterLabelSelected]}
                >
                  {item.label}
                </Text>
              </Pressable>
            );
          })}
        </ScrollView>
        {error ? (
          <View style={styles.feedback}>
            <InlineMessage message={error} />
            <AppButton
              onPress={() => void loadCatalog()}
              title="Tentar novamente"
              variant="outline"
            />
          </View>
        ) : isLoading ? (
          <LoadingState message="Pesquisando conteúdos..." />
        ) : items.length === 0 ? (
          <AppCard>
            <Text style={styles.emptyTitle}>Nenhum conteúdo encontrado</Text>
            <Text style={styles.muted}>Altere o termo ou escolha outro filtro.</Text>
          </AppCard>
        ) : (
          <>
            <Text style={styles.resultCount}>
              {total} {total === 1 ? 'resultado' : 'resultados'}
            </Text>
            {items.map((item) => (
              <AppCard key={`${item.source}-${item.id}`} style={styles.itemCard}>
                <View style={styles.itemHeading}>
                  <View style={styles.itemIcon}>
                    <Ionicons
                      color={colors.purple}
                      name={
                        item.source === 'biblioteca'
                          ? 'book-outline'
                          : 'document-text-outline'
                      }
                      size={19}
                    />
                  </View>
                  <View style={styles.itemCopy}>
                    <Text style={styles.itemTitle}>{item.title}</Text>
                    <Text style={styles.muted}>
                      {[item.typeLabel, item.subject, item.responsible]
                        .filter(Boolean)
                        .join(' · ')}
                    </Text>
                  </View>
                </View>
                {item.description ? (
                  <Text style={styles.description}>{item.description}</Text>
                ) : null}
                <View style={styles.badges}>
                  {item.category ? (
                    <Text style={styles.badge}>{item.category}</Text>
                  ) : null}
                  {item.accessLabel ? (
                    <Text style={styles.badge}>{item.accessLabel}</Text>
                  ) : null}
                </View>
              </AppCard>
            ))}
            {pages > 1 ? (
              <View style={styles.pagination}>
                <AppButton
                  disabled={page <= 1}
                  onPress={() => setPage((current) => current - 1)}
                  title="Anterior"
                  variant="outline"
                />
                <Text style={styles.pageLabel}>
                  {page} / {pages}
                </Text>
                <AppButton
                  disabled={page >= pages}
                  onPress={() => setPage((current) => current + 1)}
                  title="Próxima"
                  variant="outline"
                />
              </View>
            ) : null}
          </>
        )}
        <Text style={styles.note}>
          Os resultados usam as permissões do catálogo no servidor. A leitura detalhada e
          o progresso de materiais ainda não foram conectados à interface mobile.
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
  filterRow: { gap: spacing.sm, paddingVertical: spacing.xs },
  filter: {
    backgroundColor: colors.surface,
    borderColor: colors.border,
    borderRadius: radii.pill,
    borderWidth: 1,
    justifyContent: 'center',
    minHeight: 40,
    paddingHorizontal: spacing.md,
  },
  filterSelected: { backgroundColor: colors.purple, borderColor: colors.purple },
  filterLabel: { color: colors.muted, fontSize: 13, fontWeight: '600' },
  filterLabelSelected: { color: colors.white },
  feedback: { gap: spacing.sm },
  resultCount: { color: colors.muted, fontSize: 13, fontWeight: '600' },
  itemCard: { gap: spacing.md },
  itemHeading: { alignItems: 'center', flexDirection: 'row', gap: spacing.md },
  itemIcon: {
    alignItems: 'center',
    backgroundColor: colors.greenSoft,
    borderRadius: radii.pill,
    height: 42,
    justifyContent: 'center',
    width: 42,
  },
  itemCopy: { flex: 1, gap: spacing.xs },
  itemTitle: { color: colors.text, fontSize: 16, fontWeight: '700' },
  muted: { color: colors.muted, fontSize: 13 },
  description: { color: colors.text, fontSize: 14 },
  badges: { flexDirection: 'row', flexWrap: 'wrap', gap: spacing.xs },
  badge: {
    backgroundColor: colors.purpleSoft,
    borderRadius: radii.pill,
    color: colors.purple,
    fontSize: 11,
    overflow: 'hidden',
    paddingHorizontal: spacing.sm,
    paddingVertical: spacing.xs,
  },
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
