import 'package:flutter/material.dart';
import 'package:firebase_core/firebase_core.dart';
import 'package:provider/provider.dart';
import 'package:google_fonts/google_fonts.dart';
import 'providers/auth_provider.dart';
import 'providers/chat_provider.dart';
import 'providers/subscription_provider.dart';
import 'screens/splash_screen.dart';
import 'screens/auth/login_screen.dart';
import 'screens/auth/register_screen.dart';
import 'screens/chat/chat_screen.dart';
import 'screens/concours/concours_selection_screen.dart';
import 'screens/quiz/quiz_screen.dart';
import 'screens/quiz/quiz_result_screen.dart';
import 'screens/exam/exam_screen.dart';
import 'screens/exam/exam_result_screen.dart';
import 'screens/dashboard/dashboard_screen.dart';
import 'screens/subscription/subscription_screen.dart';
import 'screens/settings/settings_screen.dart';
import 'services/notification_service.dart';

void main() async {
  WidgetsFlutterBinding.ensureInitialized();
  
  // Initialiser Firebase (avec gestion d'erreur s'il n'y a pas de config Web)
  try {
    await Firebase.initializeApp();
  } catch (e) {
    debugPrint('Erreur d\'initialisation Firebase (ignorée) : $e');
  }
  
  runApp(const GrandFrereApp());
}

class GrandFrereApp extends StatelessWidget {
  const GrandFrereApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MultiProvider(
      providers: [
        ChangeNotifierProvider(create: (_) => AuthProvider()..initialize()),
        ChangeNotifierProvider(create: (_) => ChatProvider()),
        ChangeNotifierProvider(create: (_) => SubscriptionProvider()),
      ],
      child: MaterialApp(
        title:        'Grand Frère Intelligent',
        debugShowCheckedModeBanner: false,
        theme:        _buildTheme(),
        initialRoute: '/splash',
        routes: {
          '/splash':      (_) => const SplashScreen(),
          '/login':       (_) => const LoginScreen(),
          '/register':    (_) => const RegisterScreen(),
          '/chat':        (_) => const ChatScreen(),
          '/concours':    (_) => const ConcoursSelectionScreen(),
          '/quiz':        (_) => const QuizScreen(),
          '/quiz-result': (_) => const QuizResultScreen(),
          '/exam':        (_) => const ExamScreen(),
          '/exam-result': (_) => const ExamResultScreen(),
          '/dashboard':   (_) => const DashboardScreen(),
          '/subscription':(_) => const SubscriptionScreen(),
          '/settings':    (_) => const SettingsScreen(),
        },
      ),
    );
  }

  ThemeData _buildTheme() {
    const primaryColor   = Color(0xFF5C35D9);
    const secondaryColor = Color(0xFFF0B429);
    const bgDark         = Color(0xFF0D0B1F);
    const surfaceDark    = Color(0xFF1A1730);
    const cardDark       = Color(0xFF231E42);

    return ThemeData(
      useMaterial3:  true,
      brightness:    Brightness.dark,
      primaryColor:  primaryColor,
      scaffoldBackgroundColor: bgDark,

      colorScheme: const ColorScheme.dark(
        primary:    primaryColor,
        secondary:  secondaryColor,
        surface:    surfaceDark,
        background: bgDark,
        onPrimary:  Colors.white,
        onSecondary:Colors.black,
        onSurface:  Colors.white,
      ),

      textTheme: GoogleFonts.outfitTextTheme(
        const TextTheme(
          displayLarge: TextStyle(color: Colors.white, fontWeight: FontWeight.w800),
          displayMedium:TextStyle(color: Colors.white, fontWeight: FontWeight.w700),
          titleLarge:   TextStyle(color: Colors.white, fontWeight: FontWeight.w700),
          titleMedium:  TextStyle(color: Colors.white, fontWeight: FontWeight.w600),
          bodyLarge:    TextStyle(color: Color(0xFFE0D8FF)),
          bodyMedium:   TextStyle(color: Color(0xFFBBB5D8)),
          labelLarge:   TextStyle(color: Colors.white, fontWeight: FontWeight.w600),
        ),
      ),

      cardTheme: CardThemeData(
        color: cardDark,
        elevation: 0,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
      ),

      elevatedButtonTheme: ElevatedButtonThemeData(
        style: ElevatedButton.styleFrom(
          backgroundColor: primaryColor,
          foregroundColor: Colors.white,
          elevation:       0,
          padding:         const EdgeInsets.symmetric(vertical: 16, horizontal: 24),
          shape:           RoundedRectangleBorder(borderRadius: BorderRadius.circular(50)),
          textStyle:       GoogleFonts.outfit(fontWeight: FontWeight.w700, fontSize: 16),
        ),
      ),

      inputDecorationTheme: InputDecorationTheme(
        filled:        true,
        fillColor:     cardDark,
        border:        OutlineInputBorder(
          borderRadius: BorderRadius.circular(14),
          borderSide:   const BorderSide(color: Color(0xFF3A3060)),
        ),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(14),
          borderSide:   const BorderSide(color: Color(0xFF3A3060)),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(14),
          borderSide:   const BorderSide(color: primaryColor, width: 2),
        ),
        hintStyle:     const TextStyle(color: Color(0xFF6B6490)),
        labelStyle:    const TextStyle(color: Color(0xFF9D96C0)),
        contentPadding:const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
      ),

      appBarTheme: const AppBarTheme(
        backgroundColor: bgDark,
        elevation:       0,
        centerTitle:     true,
        foregroundColor: Colors.white,
      ),
    );
  }
}
