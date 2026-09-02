import 'package:flutter/material.dart';
import 'package:flutter_dotenv/flutter_dotenv.dart';
import 'package:supabase_flutter/supabase_flutter.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';
import 'screens/login_screen.dart';
import 'screens/dashboard_screen.dart';
import 'screens/exam_screen.dart';
import 'screens/result_screen.dart';
import 'screens/subscription_screen.dart';
import 'screens/friends_screen.dart';
import 'screens/placeholder_screen.dart';
import 'screens/predictor_screen.dart';
import 'screens/history_screen.dart';
import 'screens/leaderboard_screen.dart';
import 'screens/achievements_screen.dart';
import 'screens/flashcards_screen.dart';
import 'screens/bookmarks_screen.dart';
import 'screens/notifications_screen.dart';
import 'screens/contact_screen.dart';
import 'screens/mocks_screen.dart';
import 'screens/custom_test_screen.dart';
import 'screens/community_screen.dart';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();
  
  // Load environment variables
  await dotenv.load(fileName: ".env");

  // Initialize Supabase
  await Supabase.initialize(
    url: dotenv.env['SUPABASE_URL'] ?? '',
    anonKey: dotenv.env['SUPABASE_KEY'] ?? '',
  );

  runApp(
    MultiProvider(
      providers: [
        // Add providers here later
        Provider<String>.value(value: 'dummy'),
      ],
      child: const DDCETApp(),
    ),
  );
}

// Router configuration
final GoRouter _router = GoRouter(
  initialLocation: '/',
  routes: [
    GoRoute(
      path: '/',
      builder: (context, state) => const SplashScreen(),
    ),
    GoRoute(
      path: '/login',
      builder: (context, state) => const LoginScreen(),
    ),
    GoRoute(
      path: '/dashboard',
      builder: (context, state) => const DashboardScreen(),
    ),
    GoRoute(
      path: '/exam',
      builder: (context, state) {
        final testId = int.tryParse(state.uri.queryParameters['testId'] ?? '0') ?? 0;
        final mode = state.uri.queryParameters['mode'] ?? 'mock';
        return ExamScreen(testId: testId, mode: mode);
      },
    ),
    GoRoute(
      path: '/result/:id',
      builder: (context, state) => ResultScreen(attemptId: int.parse(state.pathParameters['id'] ?? '0')),
    ),
    GoRoute(
      path: '/subscription',
      builder: (context, state) => const SubscriptionScreen(),
    ),
    GoRoute(
      path: '/friends',
      builder: (context, state) => const FriendsScreen(),
    ),
    GoRoute(
      path: '/predictor',
      builder: (context, state) => const PredictorScreen(),
    ),
    GoRoute(
      path: '/history',
      builder: (context, state) => const HistoryScreen(),
    ),
    GoRoute(
      path: '/leaderboard',
      builder: (context, state) => const LeaderboardScreen(),
    ),
    GoRoute(
      path: '/achievements',
      builder: (context, state) => const AchievementsScreen(),
    ),
    GoRoute(
      path: '/flashcards',
      builder: (context, state) => const FlashcardsScreen(),
    ),
    GoRoute(
      path: '/bookmarks',
      builder: (context, state) => const BookmarksScreen(),
    ),
    GoRoute(
      path: '/notifications',
      builder: (context, state) => const NotificationsScreen(),
    ),
    GoRoute(
      path: '/contact',
      builder: (context, state) => const ContactScreen(),
    ),
    GoRoute(
      path: '/mocks',
      builder: (context, state) => const MocksScreen(),
    ),
    GoRoute(
      path: '/custom_test',
      builder: (context, state) => const CustomTestScreen(),
    ),
    GoRoute(
      path: '/community',
      builder: (context, state) => const CommunityScreen(),
    ),
    GoRoute(
      path: '/placeholder/:title',
      builder: (context, state) => PlaceholderScreen(title: state.pathParameters['title'] ?? ''),
    ),
  ],
);

class DDCETApp extends StatelessWidget {
  const DDCETApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MaterialApp.router(
      title: 'DDCET Prep',
      debugShowCheckedModeBanner: false,
      theme: ThemeData(
        colorScheme: ColorScheme.fromSeed(
          seedColor: const Color(0xFF4361EE),
          brightness: Brightness.light,
        ),
        scaffoldBackgroundColor: const Color(0xFFF8F9FA),
        useMaterial3: true,
        fontFamily: 'Inter', // Assuming Inter is the main font, we'll add it to pubspec later
      ),
      routerConfig: _router,
    );
  }
}

class SplashScreen extends StatefulWidget {
  const SplashScreen({super.key});

  @override
  State<SplashScreen> createState() => _SplashScreenState();
}

class _SplashScreenState extends State<SplashScreen> {
  @override
  void initState() {
    super.initState();
    _checkAuth();
  }

  Future<void> _checkAuth() async {
    // Delay for splash screen effect
    await Future.delayed(const Duration(seconds: 2));
    
    final session = Supabase.instance.client.auth.currentSession;
    if (session != null) {
      if (mounted) context.go('/dashboard');
    } else {
      if (mounted) context.go('/login');
    }
  }

  @override
  Widget build(BuildContext context) {
    return const Scaffold(
      body: Center(
        child: CircularProgressIndicator(),
      ),
    );
  }
}


