import { StyleSheet, Text, View } from 'react-native';
import { colors, radii, spacing } from '../theme/colors';

interface InlineMessageProps {
  message: string;
  tone?: 'error' | 'info';
}

export function InlineMessage({ message, tone = 'error' }: InlineMessageProps) {
  return (
    <View accessibilityLiveRegion="polite" style={[styles.container, styles[tone]]}>
      <Text style={[styles.message, styles[`${tone}Text`]]}>{message}</Text>
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    borderRadius: radii.sm,
    borderWidth: 1,
    padding: spacing.md,
  },
  error: {
    backgroundColor: colors.dangerSoft,
    borderColor: colors.dangerBorder,
  },
  info: {
    backgroundColor: colors.purpleSoft,
    borderColor: colors.border,
  },
  message: {
    fontSize: 14,
  },
  errorText: {
    color: colors.danger,
  },
  infoText: {
    color: colors.purpleDeep,
  },
});
