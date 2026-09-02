import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:supabase_flutter/supabase_flutter.dart';
import '../services/student_service.dart';

class TestsTab extends StatefulWidget {
  const TestsTab({super.key});

  @override
  State<TestsTab> createState() => _TestsTabState();
}

class _TestsTabState extends State<TestsTab> {
  bool _isLoading = true;
  List<Map<String, dynamic>> _tests = [];
  String _plan = 'free';
  bool _hasDoneDaily = false;

  final _planHierarchy = {'free': 0, 'basic': 1, 'pro': 2};

  final _modes = [
    {'mode': 'full_mock', 'title': 'Full DDCET Mock', 'desc': '100 Qs, 200 marks, 150 min', 'icon': Icons.assignment, 'min_plan': 'basic'},
    {'mode': 'be01_paper', 'title': 'BE-01 Paper', 'desc': 'Science & Engineering – 50 Qs, 75 min', 'icon': Icons.science, 'min_plan': 'basic'},
    {'mode': 'be02_paper', 'title': 'BE-02 Paper', 'desc': 'Maths & Soft Skills – 50 Qs, 75 min', 'icon': Icons.calculate, 'min_plan': 'basic'},
    {'mode': 'rapid_fire', 'title': 'Rapid Fire', 'desc': '30 questions, 60 sec each', 'icon': Icons.bolt, 'min_plan': 'basic'},
    {'mode': 'subject_wise', 'title': 'Subject-wise Test', 'desc': 'Practice by topic', 'icon': Icons.book, 'min_plan': 'basic'},
    {'mode': 'previous_year', 'title': 'Previous Year Papers', 'desc': 'Past DDCET papers – Pro', 'icon': Icons.history_edu, 'min_plan': 'pro'},
    {'mode': 'revision', 'title': 'Revision Mode', 'desc': 'Re-attempt wrong questions', 'icon': Icons.refresh, 'min_plan': 'basic'},
  ];

  @override
  void initState() {
    super.initState();
    _fetchData();
  }

  Future<void> _fetchData() async {
    final studentId = await StudentService.getStudentId();

    try {
      // Fetch subscription
      if (studentId != null) {
        final subRows = await Supabase.instance.client
            .from('subscriptions')
            .select('plan')
            .eq('student_id', studentId)
            .eq('status', 'active')
            .gte('expires_at', DateTime.now().toIso8601String())
            .limit(1);
        if (subRows.isNotEmpty) {
          _plan = subRows[0]['plan'] ?? 'free';
        }

        // Check daily challenge
        final today = DateTime.now().toIso8601String().substring(0, 10);
        final dailyAttempts = await Supabase.instance.client
            .from('attempts')
            .select('id')
            .eq('student_id', studentId)
            .eq('mode', 'daily_challenge')
            .gte('started_at', today)
            .limit(1);
        _hasDoneDaily = dailyAttempts.isNotEmpty;
      }

      // Fetch mock tests
      final res = await Supabase.instance.client
          .from('tests')
          .select('*')
          .eq('is_published', true)
          .order('created_at', ascending: false);

      if (mounted) {
        setState(() {
          _tests = List<Map<String, dynamic>>.from(res);
          _isLoading = false;
        });
      }
    } catch (e) {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_isLoading) {
      return const Center(child: CircularProgressIndicator());
    }

    final userLevel = _planHierarchy[_plan] ?? 0;

    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        const Text('Test Modes', style: TextStyle(fontSize: 22, fontWeight: FontWeight.w800)),
        const SizedBox(height: 4),
        Text('Pick a testing format to practice.', style: TextStyle(color: Colors.grey[600], fontSize: 13)),
        const SizedBox(height: 16),

        // Daily Challenge card
        Container(
          padding: const EdgeInsets.all(16),
          decoration: BoxDecoration(
            gradient: const LinearGradient(colors: [Color(0xFF10B981), Color(0xFF059669)]),
            borderRadius: BorderRadius.circular(12),
          ),
          child: Row(
            children: [
              const Icon(Icons.local_fire_department, color: Colors.white, size: 36),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text('Daily Challenge', style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 16)),
                    const SizedBox(height: 2),
                    Text(
                      _hasDoneDaily ? 'Done for today! Come back tomorrow.' : '10 questions • Earn XP & keep your streak!',
                      style: const TextStyle(color: Colors.white70, fontSize: 12),
                    ),
                  ],
                ),
              ),
              ElevatedButton(
                onPressed: _hasDoneDaily ? null : () => context.push('/exam?mode=daily_challenge'),
                style: ElevatedButton.styleFrom(
                  backgroundColor: Colors.white,
                  foregroundColor: const Color(0xFF10B981),
                  disabledBackgroundColor: Colors.white38,
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                ),
                child: Text(_hasDoneDaily ? 'Done ✓' : 'Play'),
              ),
            ],
          ),
        ),

        const SizedBox(height: 20),

        // Mode cards grid
        GridView.builder(
          shrinkWrap: true,
          physics: const NeverScrollableScrollPhysics(),
          gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
            crossAxisCount: 2, crossAxisSpacing: 12, mainAxisSpacing: 12, childAspectRatio: 0.82,
          ),
          itemCount: _modes.length,
          itemBuilder: (context, index) {
            final mode = _modes[index];
            final requiredLevel = _planHierarchy[mode['min_plan'] as String] ?? 0;
            final locked = userLevel < requiredLevel;
            return _buildModeCard(mode, locked);
          },
        ),

        const SizedBox(height: 24),

        // Available Tests
        if (_tests.isNotEmpty) ...[
          const Text('Available Mock Tests', style: TextStyle(fontSize: 20, fontWeight: FontWeight.bold)),
          const SizedBox(height: 12),
          ..._tests.map((test) => Card(
            color: Colors.white,
            margin: const EdgeInsets.only(bottom: 10),
            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
            child: ListTile(
              contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
              leading: Container(
                width: 40, height: 40,
                decoration: BoxDecoration(
                  color: const Color(0xFFEFF6FF),
                  borderRadius: BorderRadius.circular(8),
                ),
                child: const Icon(Icons.assignment, color: Color(0xFF4361EE)),
              ),
              title: Text(test['title'] ?? 'Mock Test', style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 14)),
              subtitle: Text('${test['total_questions'] ?? '?'} Qs • ${test['duration_minutes'] ?? '?'} min', style: TextStyle(fontSize: 12, color: Colors.grey[600])),
              trailing: ElevatedButton(
                onPressed: () => context.push('/exam?testId=${test['id']}&mode=mock'),
                style: ElevatedButton.styleFrom(
                  backgroundColor: const Color(0xFF4361EE), foregroundColor: Colors.white,
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                  padding: const EdgeInsets.symmetric(horizontal: 16),
                ),
                child: const Text('Start', style: TextStyle(fontSize: 13)),
              ),
            ),
          )),
        ],
      ],
    );
  }

  Widget _buildModeCard(Map<String, dynamic> mode, bool locked) {
    final btnColors = {
      'full_mock': const Color(0xFF4361EE),
      'be01_paper': const Color(0xFF4361EE),
      'be02_paper': const Color(0xFF4361EE),
      'rapid_fire': const Color(0xFFF97316),
      'subject_wise': const Color(0xFF4361EE),
      'previous_year': const Color(0xFF8B5CF6),
      'revision': const Color(0xFF6B7280),
    };

    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: const Color(0xFFE2E8F0)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            padding: const EdgeInsets.all(8),
            decoration: BoxDecoration(
              color: locked ? Colors.grey[100] : const Color(0xFFEFF6FF),
              borderRadius: BorderRadius.circular(8),
            ),
            child: Icon(mode['icon'] as IconData, color: locked ? Colors.grey : const Color(0xFF4361EE), size: 24),
          ),
          const SizedBox(height: 10),
          Text(mode['title'] as String, style: TextStyle(fontWeight: FontWeight.bold, fontSize: 14, color: locked ? Colors.grey : null)),
          const SizedBox(height: 4),
          Expanded(child: Text(mode['desc'] as String, style: TextStyle(fontSize: 11, color: Colors.grey[locked ? 400 : 600]))),
          const SizedBox(height: 8),
          if (locked)
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
              decoration: BoxDecoration(
                color: const Color(0xFFFEF3C7), borderRadius: BorderRadius.circular(6),
              ),
              child: Row(
                mainAxisSize: MainAxisSize.min,
                children: [
                  const Icon(Icons.lock, size: 12, color: Color(0xFFF59E0B)),
                  const SizedBox(width: 4),
                  Text('${(mode['min_plan'] as String).substring(0, 1).toUpperCase()}${(mode['min_plan'] as String).substring(1)} Plan',
                      style: const TextStyle(fontSize: 11, fontWeight: FontWeight.bold, color: Color(0xFFF59E0B))),
                ],
              ),
            )
          else
            SizedBox(
              width: double.infinity,
              child: ElevatedButton(
                onPressed: () => context.push('/exam?mode=${mode['mode']}'),
                style: ElevatedButton.styleFrom(
                  backgroundColor: btnColors[mode['mode']] ?? const Color(0xFF4361EE),
                  foregroundColor: Colors.white,
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                  padding: const EdgeInsets.symmetric(vertical: 8),
                ),
                child: const Text('Start →', style: TextStyle(fontSize: 13, fontWeight: FontWeight.bold)),
              ),
            ),
        ],
      ),
    );
  }
}
