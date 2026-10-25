import { Ionicons } from '@expo/vector-icons';
import { Redirect, Tabs } from 'expo-router';
import { StyleSheet, useWindowDimensions } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { LoadingState } from '../../components/LoadingState';
import { useAuth } from '../../context/AuthContext';
import { colors } from '../../theme/colors';

const icons = {
  index: 'home-outline',
  home: 'home-outline',
  rooms: 'people-outline',
  library: 'book-outline',
  feed: 'newspaper-outline',
  questions: 'help-circle-outline',
  profile: 'person-circle-outline',
  ranking: 'trophy-outline',
} as const;

const accessibilityLabels = {
  index: 'Início',
  rooms: 'Salas',
  library: 'Biblioteca',
  feed: 'Feed',
  questions: 'Questões',
  profile: 'Perfil',
} as const;

export default function AppTabsLayout() {
  const { isLoading, session } = useAuth();
  const { fontScale, width } = useWindowDimensions();

  if (isLoading) {
    return (
      <SafeAreaView style={styles.loading}>
        <LoadingState message="Verificando sua sessão..." />
      </SafeAreaView>
    );
  }
  if (!session) {
    return <Redirect href="/" />;
  }

  return (
    <Tabs
      backBehavior="history"
      screenOptions={({ route }) => ({
        tabBarAccessibilityLabel:
          accessibilityLabels[route.name as keyof typeof accessibilityLabels] ??
          route.name,
        tabBarActiveTintColor: colors.purple,
        tabBarInactiveTintColor: colors.muted,
        tabBarHideOnKeyboard: true,
        tabBarShowLabel: width >= 380 && fontScale < 1.2,
        tabBarItemStyle: { minHeight: 48, paddingHorizontal: 0 },
        tabBarIcon: ({ color, size }) => (
          <Ionicons
            color={color}
            name={icons[route.name as keyof typeof icons] ?? 'ellipse-outline'}
            size={size}
          />
        ),
        tabBarLabelStyle: {
          fontSize: 10,
          fontWeight: '600',
          includeFontPadding: false,
          textAlign: 'center',
        },
        tabBarStyle: {
          backgroundColor: colors.surface,
          borderTopColor: colors.border,
          minHeight: 62,
          paddingTop: 5,
        },
        headerShown: false,
        sceneStyle: { backgroundColor: colors.background },
      })}
    >
      <Tabs.Screen name="index" options={{ title: 'Início', tabBarLabel: 'Início' }} />
      <Tabs.Screen name="rooms" options={{ title: 'Salas', tabBarLabel: 'Salas' }} />
      <Tabs.Screen
        name="library"
        options={{ title: 'Biblioteca', tabBarLabel: 'Biblioteca' }}
      />
      <Tabs.Screen name="feed" options={{ title: 'Feed', tabBarLabel: 'Feed' }} />
      <Tabs.Screen
        name="questions"
        options={{ title: 'Questões', tabBarLabel: 'Questões' }}
      />
      <Tabs.Screen name="profile" options={{ title: 'Perfil', tabBarLabel: 'Perfil' }} />
      <Tabs.Screen name="ranking" options={{ href: null, title: 'Ranking' }} />
    </Tabs>
  );
}

const styles = StyleSheet.create({
  loading: { backgroundColor: colors.background, flex: 1 },
});
