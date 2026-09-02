import 'package:flutter/material.dart';
import 'package:supabase_flutter/supabase_flutter.dart';
import 'package:go_router/go_router.dart';
import '../services/student_service.dart';

class HistoryScreen extends StatefulWidget {
  const HistoryScreen({super.key});

  @override
  State<HistoryScreen> createState() => _HistoryScreenState();
}

class _HistoryScreenState extends State<HistoryScreen> {
  bool _isLoading = true;
  List<Map<String, dynamic>> _attempts = [];
  Map<int, Map<String, dynamic>> _testMap = {};

  @override
  void initState() {
    super.initState();
    _fetchHistory();
  }

  Future<void> _fetchHistory() async {
    final studentId = await StudentService.getStudentId();
    if (studentId == null) {
      if (mounted) setState(() => _isLoading = false);
      return;
    }

    try {
      final rows = await Supabase.instance.client
          .from('attempts')
          .select('*')
          .eq('student_id', studentId)
          .eq('status', 'completed')
          .order('completed_at', ascending: false);

      _attempts = List<Map<String, dynamic>>.from(rows);

      // Get test titles
      final testIds = _attempts
          .map((a) => a['test_id'])
          .where((id) => id != null)
          .toSet()
          .toList();

      if (testIds.isNotEmpty) {
        final tests = await Supabase.instance.client
            .from('tests')
            .select('id,title,mode')
            .inFilter('id', testIds);
        for (var t in tests) {
          _testMap[t['id'] as int] = t as Map<String, dynamic>;
        }
      }

      if (mounted) setState(() => _isLoading = false);
    } catch (e) {
      if (mounted) {
        setState(() => _isLoading = false);
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Error: $e'), backgroundColor: Colors.red),
        );
      }
    }
  }

  String _modeLabel(String? mode) {
    switch (mode) {
      case 'full_mock': return 'Full Mock';
      case 'rapid_fire': return 'Rapid Fire';
      case 'subject_wise': return 'Subject Test';
      case 'previous_year': return 'Prev Year';
      case 'revision': return 'Revision';
      case 'daily_challenge': return 'Daily Challenge';
      case 'be01_paper': return 'BE-01';
      case 'be02_paper': return 'BE-02';
      default: return 'Practice';
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFF8F9FA),
      appBar: AppBar(
        title: const Text('Test History', style: TextStyle(fontWeight: FontWeight.bold)),
        leading: IconButton(icon: const Icon(Icons.arrow_back), onPressed: () => context.go('/dashboard')),
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator())
          : _attempts.isEmpty
              ? Center(
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Icon(Icons.assignment_outlined, size: 64, color: Colors.grey[400]),
                      const SizedBox(height: 16),
                      const Text('No tests taken yet', style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
                      const SizedBox(height: 8),
                      Text('Start a test to see your history here.', style: TextStyle(color: Colors.grey[600])),
                    ],
                  ),
                )
              : ListView.builder(
                  padding: const EdgeInsets.all(16),
                  itemCount: _attempts.length + 1,
                  itemBuilder: (context, index) {
                    if (index == 0) {
                      return Padding(
                        padding: const EdgeInsets.only(bottom: 16),
                        child: Text('${_attempts.length} tests completed',
                            style: TextStyle(color: Colors.grey[600], fontSize: 13)),
                      );
                    }
                    final a = _attempts[index - 1];
                    final test = _testMap[a['test_id'] ?? 0];
                    final title = test?['title'] ?? _modeLabel(a['mode']);
                    final score = a['score'] ?? 0;
                    final total = a['total_marks'] ?? 0;
                    final pct = total > 0 ? (score * 100 / total).round() : 0;
                    final correct = a['correct_count'] ?? 0;
                    final timeMin = ((a['time_spent_seconds'] ?? 0) / 60).round();
                    final date = a['completed_at'] != null
                        ? DateTime.tryParse(a['completed_at'])
                        : null;
                    final dateStr = date != null
                        ? '${date.day}/${date.month}/${date.year}'
                        : '';

                    return Card(
                      margin: const EdgeInsets.only(bottom: 10),
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                      child: InkWell(
                        borderRadius: BorderRadius.circular(12),
                        onTap: () => context.push('/result/${a['id']}'),
                        child: Padding(
                          padding: const EdgeInsets.all(14),
                          child: Row(
                            children: [
                              // Score circle
                              Container(
                                width: 48, height: 48,
                                decoration: BoxDecoration(
                                  shape: BoxShape.circle,
                                  color: pct >= 70
                                      ? Colors.green.withValues(alpha: 0.1)
                                      : (pct >= 40 ? Colors.orange.withValues(alpha: 0.1) : Colors.red.withValues(alpha: 0.1)),
                                ),
                                child: Center(
                                  child: Text('$pct%',
                                      style: TextStyle(
                                        fontWeight: FontWeight.bold,
                                        fontSize: 14,
                                        color: pct >= 70 ? Colors.green : (pct >= 40 ? Colors.orange : Colors.red),
                                      )),
                                ),
                              ),
                              const SizedBox(width: 12),
                              Expanded(
                                child: Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    Text(title, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 14)),
                                    const SizedBox(height: 4),
                                    Text('$correct correct • ${timeMin}m • $dateStr',
                                        style: TextStyle(fontSize: 12, color: Colors.grey[600])),
                                  ],
                                ),
                              ),
                              const Icon(Icons.chevron_right, color: Colors.grey),
                            ],
                          ),
                        ),
                      ),
                    );
                  },
                ),
    );
  }
}
