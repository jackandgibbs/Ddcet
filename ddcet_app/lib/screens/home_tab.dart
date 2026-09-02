import 'package:flutter/material.dart';
import 'package:supabase_flutter/supabase_flutter.dart';
import 'package:go_router/go_router.dart';
import '../services/student_service.dart';

class HomeTab extends StatefulWidget {
  const HomeTab({super.key});

  @override
  State<HomeTab> createState() => _HomeTabState();
}

class _HomeTabState extends State<HomeTab> {
  bool _isLoading = true;
  int _daysLeft = 0;
  int _readiness = 0;
  int _attemptCount = 0;
  String _rank = '-';
  
  Map<String, dynamic>? _featuredMock;
  String _featuredStatus = '';
  
  @override
  void initState() {
    super.initState();
    _fetchDashboardData();
  }

  Future<void> _fetchDashboardData() async {
    // Resolve integer student_id from the students table (the DB uses int PK, not UUID)
    final studentId = await StudentService.getStudentId();

    try {
      // Parallel fetches
      final futures = await Future.wait([
        // Attempts — only fetch if we have a real student id
        if (studentId != null)
          Supabase.instance.client
              .from('attempts')
              .select('id,score,total_marks,completed_at,xp_earned,test_id')
              .eq('student_id', studentId)
              .eq('status', 'completed')
              .order('completed_at', ascending: false)
        else
          Future.value(<dynamic>[]),
            
        // Tests (scheduled mocks) — no student filter needed
        Supabase.instance.client
            .from('tests')
            .select('id,title,series_label,opens_at,closes_at,results_at,results_published,is_free,min_plan')
            .eq('is_scheduled', true)
            .eq('is_published', true)
            .order('opens_at', ascending: false)
            .limit(10),
      ]);

      final attempts = futures[0] as List<dynamic>;
      final schedMocks = futures[1] as List<dynamic>;
      
      // Calculate stats
      int attemptCount = attempts.length;
      int avgScorePercent = 0;
      int readiness = 0;
      
      if (attemptCount > 0) {
        double totalPct = 0;
        for (var a in attempts) {
          if (a['total_marks'] > 0) {
            totalPct += (a['score'] * 100.0 / a['total_marks']);
          }
        }
        avgScorePercent = (totalPct / attemptCount).round();

        // Readiness: avg of last 5
        final last5 = attempts.take(5).toList();
        double rPct = 0;
        for (var a in last5) {
          if (a['total_marks'] > 0) {
            rPct += (a['score'] * 100.0 / a['total_marks']);
          }
        }
        readiness = (rPct / last5.length).round();
      }

      // Find featured mock
      Map<String, dynamic>? featuredMock;
      String featuredStatus = '';
      int bestRank = 99;
      final priority = {'live': 0, 'upcoming': 1, 'results': 2, 'grading': 3};
      
      for (var sm in schedMocks) {
        final now = DateTime.now();
        final opens = sm['opens_at'] != null ? DateTime.parse(sm['opens_at']) : null;
        final closes = sm['closes_at'] != null ? DateTime.parse(sm['closes_at']) : null;
        
        String st = 'grading';
        if (opens != null && closes != null) {
          if (now.isBefore(opens)) st = 'upcoming';
          else if (now.isAfter(opens) && now.isBefore(closes)) st = 'live';
          else if (sm['results_published'] == true) st = 'results';
        }
        
        if ((priority[st] ?? 9) < bestRank) {
          bestRank = priority[st] ?? 9;
          featuredMock = sm;
          featuredStatus = st;
        }
      }

      // Calculate days left to exam (Assume April 15, 2027 for DDCET)
      final examDate = DateTime(2027, 4, 15);
      final daysLeft = examDate.difference(DateTime.now()).inDays;

      if (mounted) {
        setState(() {
          _attemptCount = attemptCount;
          _readiness = readiness;
          _daysLeft = daysLeft > 0 ? daysLeft : 0;
          _featuredMock = featuredMock;
          _featuredStatus = featuredStatus;
          _isLoading = false;
        });
      }
    } catch (e) {
      if (mounted) {
        setState(() => _isLoading = false);
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Error loading dashboard: $e'), backgroundColor: Colors.red),
        );
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_isLoading) {
      return const Center(child: CircularProgressIndicator());
    }

    return RefreshIndicator(
      onRefresh: _fetchDashboardData,
      child: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          // Featured Mock Card
          if (_featuredMock != null)
            _buildFeaturedMockCard(),

          const SizedBox(height: 16),
          
          // Stats Grid
          _buildStatsGrid(),
          
          const SizedBox(height: 24),
          
          if (_attemptCount == 0)
            _buildGettingStarted()
          else
            _buildNextMove(),
        ],
      ),
    );
  }

  Widget _buildFeaturedMockCard() {
    Color badgeColor = Colors.grey;
    String badgeText = '';
    
    if (_featuredStatus == 'live') {
      badgeColor = Colors.green;
      badgeText = '● LIVE NOW';
    } else if (_featuredStatus == 'upcoming') {
      badgeColor = Colors.purple;
      badgeText = 'UPCOMING';
    } else {
      badgeColor = const Color(0xFF4361EE);
      badgeText = 'RESULTS OUT';
    }

    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: const Color(0xFFEFF6FF), // accent-light
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: const Color(0xFFBFDBFE)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                decoration: BoxDecoration(
                  color: badgeColor,
                  borderRadius: BorderRadius.circular(4),
                ),
                child: Text(
                  badgeText,
                  style: const TextStyle(color: Colors.white, fontSize: 10, fontWeight: FontWeight.bold),
                ),
              ),
              if (_featuredMock?['is_free'] == true) ...[
                const SizedBox(width: 8),
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                  decoration: BoxDecoration(
                    color: Colors.green,
                    borderRadius: BorderRadius.circular(4),
                  ),
                  child: const Text(
                    'FREE',
                    style: TextStyle(color: Colors.white, fontSize: 10, fontWeight: FontWeight.bold),
                  ),
                ),
              ]
            ],
          ),
          const SizedBox(height: 8),
          Text(
            _featuredMock?['series_label'] ?? _featuredMock?['title'] ?? 'Mock Test',
            style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w800),
          ),
          const SizedBox(height: 4),
          Text(
            'Check schedule for details',
            style: TextStyle(fontSize: 12, color: Colors.grey[700]),
          ),
          const SizedBox(height: 12),
          SizedBox(
            width: double.infinity,
            child: ElevatedButton(
              onPressed: () {
                context.push('/exam');
              },
              style: ElevatedButton.styleFrom(
                backgroundColor: const Color(0xFFF97316), // btn-orange
                foregroundColor: Colors.white,
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
              ),
              child: Text(
                _featuredStatus == 'live' ? 'Take it now →' : (_featuredStatus == 'upcoming' ? 'Register →' : 'See your rank →'),
                style: const TextStyle(fontWeight: FontWeight.bold),
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildStatsGrid() {
    return LayoutBuilder(
      builder: (context, constraints) {
        final crossAxisCount = constraints.maxWidth > 600 ? 4 : 2;
        return GridView.count(
          shrinkWrap: true,
          physics: const NeverScrollableScrollPhysics(),
          crossAxisCount: crossAxisCount,
          crossAxisSpacing: 12,
          mainAxisSpacing: 12,
          childAspectRatio: 1.5,
          children: [
            _statCard(_daysLeft.toString(), 'Days to DDCET', const Color(0xFFEEF2FF), const Color(0xFF4F46E5)),
            _statCard('$_readiness%', 'Readiness Score', const Color(0xFFDCFCE7), const Color(0xFF16A34A)),
            _statCard(_attemptCount.toString(), 'Tests Completed', const Color(0xFFE0F2FE), const Color(0xFF0284C7)),
            _statCard(_rank, 'Global Rank', const Color(0xFFF1F5F9), const Color(0xFF475569)),
          ],
        );
      },
    );
  }

  Widget _statCard(String value, String label, Color bgColor, Color textColor) {
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: bgColor,
        borderRadius: BorderRadius.circular(12),
      ),
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Text(
            value,
            style: TextStyle(fontSize: 24, fontWeight: FontWeight.w800, color: textColor),
          ),
          const SizedBox(height: 4),
          Text(
            label,
            style: TextStyle(fontSize: 12, fontWeight: FontWeight.w600, color: textColor.withOpacity(0.8)),
            textAlign: TextAlign.center,
          ),
        ],
      ),
    );
  }

  Widget _buildGettingStarted() {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: const Color(0xFFE2E8F0)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: const [
              Icon(Icons.flag, color: Color(0xFF4361EE), size: 20),
              SizedBox(width: 8),
              Text('Get started — 3 quick steps', style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold)),
            ],
          ),
          const SizedBox(height: 8),
          const Text('Finish these to unlock your stats, rank and a personalised study plan.', style: TextStyle(fontSize: 13, color: Colors.grey)),
          const SizedBox(height: 16),
          _stepItem('Set your branch for a tailored study plan', 'Set branch', true),
          _stepItem('Take your first practice test', 'Start a test', false),
          _stepItem('Try today\'s Daily Challenge', 'Play', false),
        ],
      ),
    );
  }

  Widget _stepItem(String label, String cta, bool isDone) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: Row(
        children: [
          Container(
            width: 24,
            height: 24,
            decoration: BoxDecoration(
              color: isDone ? Colors.green : Colors.transparent,
              border: Border.all(color: isDone ? Colors.green : Colors.grey),
              shape: BoxShape.circle,
            ),
            child: isDone ? const Icon(Icons.check, color: Colors.white, size: 16) : null,
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Text(
              label,
              style: TextStyle(
                fontSize: 13,
                color: isDone ? Colors.grey : Colors.black87,
                decoration: isDone ? TextDecoration.lineThrough : null,
              ),
            ),
          ),
          if (!isDone)
            TextButton(
              onPressed: () {},
              style: TextButton.styleFrom(
                padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 0),
                backgroundColor: const Color(0xFFF1F5F9),
              ),
              child: Text(cta, style: const TextStyle(fontSize: 12, fontWeight: FontWeight.bold)),
            )
        ],
      ),
    );
  }

  Widget _buildNextMove() {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(12),
        border: const Border(left: BorderSide(color: Color(0xFF4361EE), width: 4)),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withOpacity(0.05),
            blurRadius: 10,
            offset: const Offset(0, 4),
          ),
        ],
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Icon(Icons.track_changes, color: Color(0xFF4361EE), size: 28),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text('YOUR NEXT MOVE', style: TextStyle(fontSize: 11, fontWeight: FontWeight.bold, color: Color(0xFF4361EE), letterSpacing: 0.5)),
                const SizedBox(height: 4),
                const Text('Keep the momentum going', style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold)),
                const SizedBox(height: 4),
                Text('Your readiness is $_readiness%. One more test today nudges it up — and keeps your streak alive.', style: const TextStyle(fontSize: 13, color: Colors.grey)),
                const SizedBox(height: 12),
                ElevatedButton(
                  onPressed: () {
                    context.push('/exam');
                  },
                  style: ElevatedButton.styleFrom(
                    backgroundColor: const Color(0xFF4361EE),
                    foregroundColor: Colors.white,
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                  ),
                  child: const Text('Start a test →'),
                )
              ],
            ),
          ),
        ],
      ),
    );
  }
}
