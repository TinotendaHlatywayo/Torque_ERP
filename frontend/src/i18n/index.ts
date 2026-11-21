export type Locale = 'en' | 'sn' | 'nd' | 'af' | 'fr' | 'pt'

export const defaultLocale: Locale = 'en'

export const localeLabels: Record<Locale, string> = {
  en: 'English',
  sn: 'Shona',
  nd: 'Ndebele',
  af: 'Afrikaans',
  fr: 'Français',
  pt: 'Português',
}

export const translations: Record<Locale, Record<string, string>> = {
  en: {
    'app.name': 'TorqueERP',
    'nav.home': 'Home',
    'nav.login': 'Login',
    'auth.login': 'Sign in',
    'auth.email': 'Email',
    'auth.password': 'Password',
    'common.loading': 'Loading...',
    'common.error': 'Something went wrong',
    'common.empty': 'No data',
    'common.retry': 'Try again',
    'common.accessDenied': 'Access denied',
  },
  sn: {
    'app.name': 'TorqueERP',
    'nav.home': 'Kumba',
    'nav.login': 'Pinda',
    'auth.login': 'Pinda',
    'auth.email': 'Email',
    'auth.password': 'Pasiwedhi',
    'common.loading': 'Kumirira...',
    'common.error': 'Pane chakanganisika',
    'common.empty': 'Hapana data',
    'common.retry': 'Edza zvakare',
    'common.accessDenied': 'Haurikubvumidzwa',
  },
  nd: {
    'app.name': 'TorqueERP',
    'nav.home': 'Ikhaya',
    'nav.login': 'Ngena',
    'auth.login': 'Ngena',
    'auth.email': 'I-imeyili',
    'auth.password': 'Iphasiwedi',
    'common.loading': 'Kuyalayishwa...',
    'common.error': 'Kulento engalunganga',
    'common.empty': 'Akula datha',
    'common.retry': 'Zama njalo',
    'common.accessDenied': 'Ukufinyelela kuvinjiwe',
  },
  af: {
    'app.name': 'TorqueERP',
    'nav.home': 'Tuis',
    'nav.login': 'Meld aan',
    'auth.login': 'Meld aan',
    'auth.email': 'E-pos',
    'auth.password': 'Wagwoord',
    'common.loading': 'Laai tans...',
    'common.error': 'Iets het verkeerd geloop',
    'common.empty': 'Geen data nie',
    'common.retry': 'Probeer weer',
    'common.accessDenied': 'Toegang geweier',
  },
  fr: {
    'app.name': 'TorqueERP',
    'nav.home': 'Accueil',
    'nav.login': 'Connexion',
    'auth.login': 'Se connecter',
    'auth.email': 'E-mail',
    'auth.password': 'Mot de passe',
    'common.loading': 'Chargement...',
    'common.error': 'Une erreur est survenue',
    'common.empty': 'Aucune donnée',
    'common.retry': 'Réessayer',
    'common.accessDenied': 'Accès refusé',
  },
  pt: {
    'app.name': 'TorqueERP',
    'nav.home': 'Início',
    'nav.login': 'Entrar',
    'auth.login': 'Entrar',
    'auth.email': 'E-mail',
    'auth.password': 'Palavra-passe',
    'common.loading': 'A carregar...',
    'common.error': 'Algo correu mal',
    'common.empty': 'Sem dados',
    'common.retry': 'Tentar novamente',
    'common.accessDenied': 'Acesso negado',
  },
}

export function t(locale: Locale, key: string): string {
  return translations[locale]?.[key] || translations[defaultLocale][key] || key
}
