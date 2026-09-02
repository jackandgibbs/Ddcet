import 'package:flutter/material.dart';
import 'package:supabase_flutter/supabase_flutter.dart';
import 'package:go_router/go_router.dart';
import '../services/student_service.dart';

class LeaderboardScreen extends StatefulWidget {
  const LeaderboardScreen({super.key});

  @override
  State<LeaderboardScreen> createState() => _LeaderboardScreenState();
}

class _LeaderboardScreenState extends State<LeaderboardScreen> with SingleTickerProviderStateMixin {
  late TabController _tabController;
  bool _isLoading = true;
  List<Map<String, dynamic>> _rows = [];
  int? _myStudentId;

  final _tabs = ['Global', 'Weekly', 'College', 'Streak', 'Daily'];

  @override
  void initState() {
    super.initState();
    _tabController = TabController(length: _tabs.length, vsync: this);
    _tabController.addListener(() {
      if (!_tabController.indexIsChanging) _fetchBoard();
    });
    _init();
  }

  Future<void> _init() async {
    _myStudentId = await StudentService.getStudentId();
    _fetchBoard();
  }

  @override
  void dispose() {
    _tabController.dispose();
    super.dispose();
  }

  Future<void> _fetchBoard() async {
    setState(() => _isLoading = true);
    try {
      List<Map<String, dynamic>> rows = [];
      switch (_tabController.index) {
        case 0: // Global XP
          final list = await Supabase.instance.client
              .from('students')
              .select('id,name,avatar_url,xp,level')
              .eq('is_banned', false)
              .order('xp', ascending: false)
              .limit(50);
          for (var s in list) {
            rows.add({
              'id': s['id'],
              'name': s['name'] ?? 'Student',
              'avatar_url': s['avatar_url'],
              'value': '${s['xp'] ?? 0} XP',
              'sub': s['level'] ?? '',
            });
          }
          break;

        case 1: // Weekly XP
          final since = DateTime.now().subtract(const Duration(days: 7)).toIso8601String();
          final xpRows = await Supabase.instance.client
              .from('xp_log')
              .select('student_id,amount')
              .gte('created_at', since)
              .limit(5000);
          Map<int, int> xpMap = {};
          for (var r in xpRows) {
            final sid = r['student_id'] as int? ?? 0;
            xpMap[sid] = (xpMap[sid] ?? 0) + ((r['amount'] ?? 0) as int);
          }
          final sorted = xpMap.entries.toList()..sort((a, b) => b.value.compareTo(a.value));
          final topIds = sorted.take(50).map((e) => e.key).toList();
          if (topIds.isNotEmpty) {
            final names = await Supabase.instance.client
                .from('students')
                .select('id,name,avatar_url')
                .inFilter('id', topIds);
            Map<int, Map<String, dynamic>> nameMap = {};
            for (var n in names) nameMap[n['id'] as int] = n;
            for (var e in sorted.take(50)) {
              final n = nameMap[e.key];
              if (n != null) {
                rows.add({
                  'id': e.key,
                  'name': n['name'] ?? 'Student',
                  'avatar_url': n['avatar_url'],
                  'value': '${e.value} XP',
                  'sub': 'This week',
                });
              }
            }
          }
          break;

        case 2: // College
          final profile = await StudentService.getProfile();
          final collegeId = profile?['college_id'];
          if (collegeId != null) {
            final list = await Supabase.instance.client
                .from('students')
                .select('id,name,avatar_url,xp,level')
                .eq('is_banned', false)
                .eq('college_id', collegeId)
                .order('xp', ascending: false)
                .limit(50);
            for (var s in list) {
              rows.add({
                'id': s['id'],
                'name': s['name'] ?? 'Student',
                'avatar_url': s['avatar_url'],
                'value': '${s['xp'] ?? 0} XP',
                'sub': s['level'] ?? '',
              });
            }
          }
          break;

        case 3: // Streak
          final list = await Supabase.instance.client
              .from('students')
              .select('id,name,avatar_url,daily_streak')
              .eq('is_banned', false)
              .order('daily_streak', ascending: false)
              .limit(50);
          for (var s in list) {
            rows.add({
              'id': s['id'],
              'name': s['name'] ?? 'Student',
              'avatar_url': s['avatar_url'],
              'value': '${s['daily_streak'] ?? 0} days',
              'sub': '🔥 Streak',
            });
          }
          break;

        case 4: // Daily Challenge
          final today = DateTime.now().toIso8601String().substring(0, 10);
          final att = await Supabase.instance.client
              .from('attempts')
              .select('student_id,score,correct_count')
              .eq('mode', 'daily_challenge')
              .eq('status', 'completed')
              .gte('started_at', today)
              .order('score', ascending: false)
              .limit(50);
          final sids = att.map((a) => a['student_id']).toSet().toList();
          if (sids.isNotEmpty) {
            final names = await Supabase.instance.client
                .from('students')
                .select('id,name,avatar_url')
                .inFilter('id', sids);
            Map<int, Map<String, dynamic>> nameMap = {};
            for (var n in names) nameMap[n['id'] as int] = n;
            for (var a in att) {
              final n = nameMap[a['student_id'] as int];
              if (n != null) {
                rows.add({
                  'id': a['student_id'],
                  'name': n['name'] ?? 'Student',
                  'avatar_url': n['avatar_url'],
                  'value': '${a['score']} pts',
                  'sub': '${a['correct_count'] ?? 0} correct',
                });
              }
            }
          }
          break;
      }

      if (mounted) setState(() { _rows = rows; _isLoading = false; });
    } catch (e) {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFF8F9FA),
      appBar: AppBar(
        title: const Text('Leaderboard', style: TextStyle(fontWeight: FontWeight.bold)),
        leading: IconButton(icon: const Icon(Icons.arrow_back), onPressed: () => context.go('/dashboard')),
        bottom: TabBar(
          controller: _tabController,
          isScrollable: true,
          labelColor: const Color(0xFF4361EE),
          unselectedLabelColor: Colors.grey,
          indicatorColor: const Color(0xFF4361EE),
          tabs: _tabs.map((t) => Tab(text: t)).toList(),
        ),
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator())
          : _rows.isEmpty
              ? const Center(child: Text('No data yet', style: TextStyle(color: Colors.grey)))
              : ListView.builder(
                  padding: const EdgeInsets.all(16),
                  itemCount: _rows.length,
                  itemBuilder: (context, index) {
                    final r = _rows[index];
                    final isMe = r['id'] == _myStudentId;
                    return Container(
                      margin: const EdgeInsets.only(bottom: 8),
                      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
                      decoration: BoxDecoration(
                        color: isMe ? const Color(0xFFEFF6FF) : Colors.white,
                        borderRadius: BorderRadius.circular(12),
                        border: Border.all(color: isMe ? const Color(0xFF4361EE) : const Color(0xFFE2E8F0)),
                      ),
                      child: Row(
                        children: [
                          // Rank
                          SizedBox(
                            width: 32,
                            child: Text(
                              index < 3
                                  ? ['🥇', '🥈', '🥉'][index]
                                  : '#${index + 1}',
                              style: TextStyle(
                                fontWeight: FontWeight.bold,
                                fontSize: index < 3 ? 20 : 14,
                                color: Colors.grey[700],
                              ),
                            ),
                          ),
                          const SizedBox(width: 8),
                          // Avatar
                          CircleAvatar(
                            radius: 18,
                            backgroundImage: r['avatar_url'] != null
                                ? NetworkImage(r['avatar_url'])
                                : null,
                            child: r['avatar_url'] == null
                                ? Text((r['name'] as String).substring(0, 1).toUpperCase())
                                : null,
                          ),
                          const SizedBox(width: 12),
                          // Name
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(
                                  '${r['name']}${isMe ? ' (You)' : ''}',
                                  style: TextStyle(fontWeight: FontWeight.bold, fontSize: 14, color: isMe ? const Color(0xFF4361EE) : null),
                                ),
                                if (r['sub'] != null && (r['sub'] as String).isNotEmpty)
                                  Text(r['sub'], style: TextStyle(fontSize: 11, color: Colors.grey[500])),
                              ],
                            ),
                          ),
                          // Value
                          Text(r['value'], style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 14)),
                        ],
                      ),
                    );
                  },
                ),
    );
  }
}
