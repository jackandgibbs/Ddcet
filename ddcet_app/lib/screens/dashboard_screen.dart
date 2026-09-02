import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:supabase_flutter/supabase_flutter.dart';
import '../services/student_service.dart';
import 'home_tab.dart';
import 'profile_tab.dart';
import 'tests_tab.dart';
import 'ai_tutor_tab.dart';

class DashboardScreen extends StatefulWidget {
  const DashboardScreen({super.key});

  @override
  State<DashboardScreen> createState() => _DashboardScreenState();
}

class _DashboardScreenState extends State<DashboardScreen> {
  int _currentIndex = 0;
  int _unreadNotifs = 0;
  String _userName = '';
  String? _avatarUrl;

  @override
  void initState() {
    super.initState();
    _loadUserInfo();
  }

  Future<void> _loadUserInfo() async {
    final profile = await StudentService.getProfile();
    if (profile != null && mounted) {
      setState(() {
        _userName = profile['name'] ?? 'Student';
        _avatarUrl = profile['avatar_url'];
      });
    }
    // Unread notification count
    final studentId = await StudentService.getStudentId();
    if (studentId != null) {
      try {
        final rows = await Supabase.instance.client
            .from('notifications')
            .select('id')
            .eq('student_id', studentId)
            .eq('is_read', false);
        if (mounted) setState(() => _unreadNotifs = rows.length);
      } catch (_) {}
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Row(
          children: [
            Icon(Icons.verified, color: Color(0xFF4361EE), size: 24),
            SizedBox(width: 8),
            Text('DDCET Prep', style: TextStyle(fontWeight: FontWeight.w900, color: Color(0xFF1a1a2e))),
          ],
        ),
        backgroundColor: Colors.white,
        elevation: 0,
        iconTheme: const IconThemeData(color: Colors.black87),
        actions: [
          Stack(
            children: [
              IconButton(
                icon: const Icon(Icons.notifications_none),
                onPressed: () => context.push('/notifications'),
              ),
              if (_unreadNotifs > 0)
                Positioned(
                  right: 8, top: 8,
                  child: Container(
                    width: 16, height: 16,
                    decoration: const BoxDecoration(color: Colors.red, shape: BoxShape.circle),
                    child: Center(
                      child: Text(
                        _unreadNotifs > 9 ? '9+' : '$_unreadNotifs',
                        style: const TextStyle(color: Colors.white, fontSize: 9, fontWeight: FontWeight.bold),
                      ),
                    ),
                  ),
                ),
            ],
          ),
        ],
      ),
      drawer: _buildSidebar(),
      body: _buildBody(),
      bottomNavigationBar: BottomNavigationBar(
        currentIndex: _currentIndex,
        onTap: (index) => setState(() => _currentIndex = index),
        type: BottomNavigationBarType.fixed,
        selectedItemColor: const Color(0xFF4361EE),
        unselectedItemColor: Colors.grey,
        items: const [
          BottomNavigationBarItem(icon: Icon(Icons.dashboard), label: 'Home'),
          BottomNavigationBarItem(icon: Icon(Icons.quiz), label: 'Tests'),
          BottomNavigationBarItem(icon: Icon(Icons.chat), label: 'AI Tutor'),
          BottomNavigationBarItem(icon: Icon(Icons.person), label: 'Profile'),
        ],
      ),
    );
  }

  Widget _buildSidebar() {
    return Drawer(
      child: SafeArea(
        child: ListView(
          padding: EdgeInsets.zero,
          children: [
            // User header
            Container(
              padding: const EdgeInsets.all(16),
              decoration: const BoxDecoration(
                border: Border(bottom: BorderSide(color: Color(0xFFE2E8F0))),
              ),
              child: Row(
                children: [
                  CircleAvatar(
                    radius: 20,
                    backgroundImage: _avatarUrl != null ? NetworkImage(_avatarUrl!) : null,
                    child: _avatarUrl == null ? const Icon(Icons.person) : null,
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(_userName, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 15)),
                        const Text('Student', style: TextStyle(fontSize: 12, color: Colors.grey)),
                      ],
                    ),
                  ),
                ],
              ),
            ),

            _navSection('MAIN'),
            _navItem(Icons.dashboard, 'Dashboard', () { Navigator.pop(context); setState(() => _currentIndex = 0); }),
            _navItem(Icons.description, 'Test Modes', () { Navigator.pop(context); setState(() => _currentIndex = 1); }),
            _navItem(Icons.emoji_events, 'Mock Series', () { Navigator.pop(context); context.push('/mocks'); }),
            _navItem(Icons.history, 'Test History', () { Navigator.pop(context); context.push('/history'); }),
            _navItem(Icons.tune, 'Custom Test', () { Navigator.pop(context); context.push('/custom_test'); }),
            _navItem(Icons.leaderboard, 'Leaderboard', () { Navigator.pop(context); context.push('/leaderboard'); }),
            _navItem(Icons.forum, 'Community', () { Navigator.pop(context); context.push('/community'); }),

            _navSection('STUDY'),
            _navItem(Icons.help_outline, 'Doubt Box', () { Navigator.pop(context); setState(() => _currentIndex = 2); }),
            _navItem(Icons.track_changes, 'College Predictor', () { Navigator.pop(context); context.push('/predictor'); }),
            _navItem(Icons.style, 'Flashcards', () { Navigator.pop(context); context.push('/flashcards'); }),
            _navItem(Icons.bookmark, 'Bookmarks', () { Navigator.pop(context); context.push('/bookmarks'); }),

            _navSection('SOCIAL'),
            _navItem(Icons.people, 'Friends', () { Navigator.pop(context); context.push('/friends'); }),
            _navItem(Icons.person, 'Profile', () { Navigator.pop(context); setState(() => _currentIndex = 3); }),
            _navItem(Icons.workspace_premium, 'Achievements', () { Navigator.pop(context); context.push('/achievements'); }),

            _navSection('ACCOUNT'),
            _navItem(Icons.credit_card, 'Subscription', () { Navigator.pop(context); context.push('/subscription'); }),
            _navItem(Icons.notifications, 'Notifications', () { Navigator.pop(context); context.push('/notifications'); }),
            _navItem(Icons.chat_bubble_outline, 'Contact Us', () { Navigator.pop(context); context.push('/contact'); }),
            const Divider(),
            _navItem(Icons.logout, 'Logout', () {
              Navigator.pop(context);
              StudentService.clearCache();
              Supabase.instance.client.auth.signOut();
              context.go('/login');
            }),
            const SizedBox(height: 24),
          ],
        ),
      ),
    );
  }

  Widget _navSection(String title) {
    return Padding(
      padding: const EdgeInsets.only(left: 16, top: 16, bottom: 8),
      child: Text(
        title,
        style: const TextStyle(fontSize: 12, fontWeight: FontWeight.bold, color: Colors.grey, letterSpacing: 1.2),
      ),
    );
  }

  Widget _navItem(IconData icon, String title, VoidCallback onTap) {
    return ListTile(
      leading: Icon(icon, color: Colors.black54, size: 22),
      title: Text(title, style: const TextStyle(fontSize: 14, color: Colors.black87)),
      dense: true,
      onTap: onTap,
    );
  }

  Widget _buildBody() {
    switch (_currentIndex) {
      case 0: return const HomeTab();
      case 1: return const TestsTab();
      case 2: return const AiTutorTab();
      case 3: return const ProfileTab();
      default: return const SizedBox.shrink();
    }
  }
}
