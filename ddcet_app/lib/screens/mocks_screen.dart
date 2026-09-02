import 'package:flutter/material.dart';
import 'package:supabase_flutter/supabase_flutter.dart';
import 'package:go_router/go_router.dart';
import '../services/student_service.dart';

class MocksScreen extends StatefulWidget {
  const MocksScreen({super.key});

  @override
  State<MocksScreen> createState() => _MocksScreenState();
}

class _MocksScreenState extends State<MocksScreen> {
  bool _isLoading = true;
  int _userLevel = 0;
  int? _myStudentId;
  
  Map<String, List<Map<String, dynamic>>> _buckets = {
    'live': [], 'upcoming': [], 'grading': [], 'results': []
  };
  Map<int, int> _regCounts = {};
  Map<int, bool> _myRegs = {};
  Map<int, Map<String, dynamic>> _myAttempts = {};
  
  final _planHierarchy = {'free': 0, 'basic': 1, 'pro': 2};

  @override
  void initState() {
    super.initState();
    _fetchData();
  }

  Future<void> _fetchData() async {
    _myStudentId = await StudentService.getStudentId();
    if (_myStudentId == null) {
      if (mounted) setState(() => _isLoading = false);
      return;
    }

    try {
      // 1. Get user plan
      final subRows = await Supabase.instance.client
          .from('subscriptions')
          .select('plan')
          .eq('student_id', _myStudentId!)
          .eq('status', 'active')
          .gte('expires_at', DateTime.now().toIso8601String())
          .limit(1);
          
      String plan = 'free';
      if (subRows.isNotEmpty) plan = subRows[0]['plan'] ?? 'free';
      _userLevel = _planHierarchy[plan] ?? 0;

      // 2. Fetch all scheduled mocks
      final mocksRes = await Supabase.instance.client
          .from('tests')
          .select('id,title,series_label,duration_minutes,total_questions,total_marks,opens_at,closes_at,results_at,results_published,is_free,min_plan')
          .eq('is_scheduled', true)
          .eq('is_published', true)
          .isFilter('org_id', null)
          .order('opens_at', ascending: false);
          
      List<Map<String, dynamic>> mocks = List<Map<String, dynamic>>.from(mocksRes);
      List<int> testIds = mocks.map((m) => m['id'] as int).toList();

      if (testIds.isNotEmpty) {
        // 3. Fetch user attempts
        final attRows = await Supabase.instance.client
            .from('attempts')
            .select('id,test_id,score,total_marks,percentile')
            .eq('student_id', _myStudentId!)
            .inFilter('test_id', testIds)
            .eq('status', 'completed')
            .order('completed_at', ascending: false);
            
        for (var a in attRows) {
          if (!_myAttempts.containsKey(a['test_id'])) {
            _myAttempts[a['test_id'] as int] = a;
          }
        }

        // 4. Fetch registrations (batch)
        final regRows = await Supabase.instance.client
            .from('mock_registrations')
            .select('test_id,student_id')
            .inFilter('test_id', testIds);
            
        for (var r in regRows) {
          int tid = r['test_id'];
          _regCounts[tid] = (_regCounts[tid] ?? 0) + 1;
          if (r['student_id'] == _myStudentId) {
            _myRegs[tid] = true;
          }
        }
      }

      // Group by status
      Map<String, List<Map<String, dynamic>>> buckets = {
        'live': [], 'upcoming': [], 'grading': [], 'results': []
      };
      
      final now = DateTime.now();
      for (var m in mocks) {
        final opens = m['opens_at'] != null ? DateTime.parse(m['opens_at']) : null;
        final closes = m['closes_at'] != null ? DateTime.parse(m['closes_at']) : null;
        final resultsAt = m['results_at'] != null ? DateTime.parse(m['results_at']) : null;
        final resultsPub = m['results_published'] == true;
        
        String status = 'upcoming';
        if (resultsPub || (resultsAt != null && now.isAfter(resultsAt))) {
          status = 'results';
        } else if (closes != null && now.isAfter(closes)) {
          status = 'grading';
        } else if (opens != null && now.isAfter(opens) && (closes == null || now.isBefore(closes))) {
          status = 'live';
        }
        buckets[status]!.add(m);
      }

      if (mounted) {
        setState(() {
          _buckets = buckets;
          _isLoading = false;
        });
      }
    } catch (e) {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  Future<void> _registerForMock(int testId) async {
    try {
      await Supabase.instance.client.from('mock_registrations').upsert({
        'test_id': testId,
        'student_id': _myStudentId,
      });
      setState(() {
        _myRegs[testId] = true;
        _regCounts[testId] = (_regCounts[testId] ?? 0) + 1;
      });
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Successfully registered for the mock!'), backgroundColor: Colors.green),
        );
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Failed to register: $e'), backgroundColor: Colors.red),
        );
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final bool isEmpty = _buckets.values.every((list) => list.isEmpty);

    return Scaffold(
      backgroundColor: const Color(0xFFF8F9FA),
      appBar: AppBar(
        title: const Text('Mock Series', style: TextStyle(fontWeight: FontWeight.bold)),
        leading: IconButton(icon: const Icon(Icons.arrow_back), onPressed: () => context.go('/dashboard')),
        backgroundColor: Colors.white,
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator())
          : ListView(
              padding: const EdgeInsets.all(16),
              children: [
                // Header Banner
                Container(
                  padding: const EdgeInsets.all(20),
                  decoration: BoxDecoration(
                    gradient: const LinearGradient(colors: [Color(0xFF1a1a2e), Color(0xFF2d1b69)]),
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: const Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text('DDCET Mock Series', style: TextStyle(color: Colors.white, fontSize: 20, fontWeight: FontWeight.bold)),
                      SizedBox(height: 8),
                      Text(
                        'Everyone takes the same paper in the same window. When it closes, your All-India Rank and full solutions unlock. One free mock every series — the rest are part of Pro.',
                        style: TextStyle(color: Colors.white70, fontSize: 13, height: 1.4),
                      ),
                    ],
                  ),
                ),
                
                const SizedBox(height: 24),
                
                if (isEmpty)
                  Center(
                    child: Padding(
                      padding: const EdgeInsets.all(32.0),
                      child: Column(
                        children: [
                          const Icon(Icons.calendar_month, size: 64, color: Colors.grey),
                          const SizedBox(height: 16),
                          const Text('No scheduled mocks yet', style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
                          const SizedBox(height: 8),
                          Text('The next DDCET mock will appear here.', style: TextStyle(color: Colors.grey[600], fontSize: 14)),
                          TextButton(onPressed: () => context.go('/dashboard'), child: const Text('Go to Practice Tests')),
                        ],
                      ),
                    ),
                  )
                else ...[
                  _buildSection('Live Now', 'live'),
                  _buildSection('Upcoming', 'upcoming'),
                  _buildSection('Results Out', 'results'),
                  _buildSection('Grading', 'grading'),
                ],
              ],
            ),
    );
  }

  Widget _buildSection(String title, String statusKey) {
    final items = _buckets[statusKey]!;
    if (items.isEmpty) return const SizedBox.shrink();

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Padding(
          padding: const EdgeInsets.symmetric(vertical: 12),
          child: Text(title.toUpperCase(), style: const TextStyle(fontSize: 13, fontWeight: FontWeight.bold, color: Colors.grey, letterSpacing: 1.2)),
        ),
        ...items.map((m) => _buildMockCard(m, statusKey)),
        const SizedBox(height: 12),
      ],
    );
  }

  Widget _buildMockCard(Map<String, dynamic> m, String status) {
    final int id = m['id'];
    final String title = m['series_label'] ?? m['title'] ?? 'Mock Test';
    final int regs = _regCounts[id] ?? 0;
    final myAttempt = _myAttempts[id];
    final bool registered = _myRegs[id] == true;
    
    final minPlan = m['min_plan'] ?? 'basic';
    final int needLevel = m['is_free'] == true ? 0 : (_planHierarchy[minPlan] ?? 1);
    final bool locked = _userLevel < needLevel;

    // Badges UI
    Color statusColor;
    String statusText;
    if (status == 'live') { statusColor = Colors.green; statusText = '● LIVE NOW'; }
    else if (status == 'upcoming') { statusColor = Colors.purple; statusText = 'UPCOMING'; }
    else if (status == 'results') { statusColor = const Color(0xFF4361EE); statusText = 'RESULTS OUT'; }
    else { statusColor = Colors.grey; statusText = 'GRADING'; }

    return Container(
      margin: const EdgeInsets.only(bottom: 16),
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
            children: [
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                decoration: BoxDecoration(color: statusColor.withValues(alpha: 0.1), borderRadius: BorderRadius.circular(4)),
                child: Text(statusText, style: TextStyle(fontSize: 10, fontWeight: FontWeight.bold, color: statusColor)),
              ),
              const SizedBox(width: 8),
              if (m['is_free'] == true)
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                  decoration: BoxDecoration(color: Colors.green.withValues(alpha: 0.1), borderRadius: BorderRadius.circular(4)),
                  child: const Text('FREE', style: TextStyle(fontSize: 10, fontWeight: FontWeight.bold, color: Colors.green)),
                )
              else
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                  decoration: BoxDecoration(color: const Color(0xFFEFF6FF), borderRadius: BorderRadius.circular(4)),
                  child: Row(
                    children: [
                      const Icon(Icons.lock, size: 10, color: Color(0xFF4361EE)),
                      const SizedBox(width: 4),
                      Text(minPlan.toUpperCase(), style: const TextStyle(fontSize: 10, fontWeight: FontWeight.bold, color: Color(0xFF4361EE))),
                    ],
                  ),
                ),
            ],
          ),
          const SizedBox(height: 12),
          Text(title, style: const TextStyle(fontSize: 17, fontWeight: FontWeight.bold)),
          const SizedBox(height: 4),
          Text('${m['total_questions'] ?? '?'} Qs · ${m['total_marks'] ?? '?'} marks · ${m['duration_minutes'] ?? '?'} min', 
              style: TextStyle(color: Colors.grey[700], fontSize: 13)),
          
          const SizedBox(height: 12),
          if (m['opens_at'] != null)
            Row(children: [const Icon(Icons.calendar_month, size: 14, color: Colors.grey), const SizedBox(width: 6), Text('Opens: ${_formatDate(m['opens_at'])}', style: TextStyle(fontSize: 12, color: Colors.grey[600]))]),
          if (m['closes_at'] != null)
            Padding(padding: const EdgeInsets.only(top: 4), child: Row(children: [const Icon(Icons.flag, size: 14, color: Colors.grey), const SizedBox(width: 6), Text('Closes: ${_formatDate(m['closes_at'])}', style: TextStyle(fontSize: 12, color: Colors.grey[600]))])),
          
          Padding(
            padding: const EdgeInsets.only(top: 8, bottom: 12),
            child: Row(children: [const Icon(Icons.people, size: 14, color: Color(0xFF4361EE)), const SizedBox(width: 6), Text('$regs aspirant${regs == 1 ? '' : 's'} registered', style: const TextStyle(fontSize: 12, color: Color(0xFF4361EE), fontWeight: FontWeight.bold))]),
          ),

          // Action Area based on status
          _buildActionArea(status, id, locked, minPlan, registered, myAttempt),
        ],
      ),
    );
  }

  Widget _buildActionArea(String status, int id, bool locked, String minPlan, bool registered, Map<String, dynamic>? myAttempt) {
    if (status == 'upcoming') {
      if (locked) return _buildUnlockBtn(minPlan);
      if (registered) {
        return Container(
          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
          decoration: BoxDecoration(color: Colors.green.withValues(alpha: 0.1), borderRadius: BorderRadius.circular(6)),
          child: const Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              Icon(Icons.check, size: 14, color: Colors.green), SizedBox(width: 6), Text('You\'re registered', style: TextStyle(fontSize: 12, color: Colors.green, fontWeight: FontWeight.bold)),
            ],
          ),
        );
      }
      return ElevatedButton(
        onPressed: () => _registerForMock(id),
        style: ElevatedButton.styleFrom(backgroundColor: Colors.purple, foregroundColor: Colors.white, minimumSize: const Size(120, 36)),
        child: const Text('Register →', style: TextStyle(fontSize: 13)),
      );
    } else if (status == 'live') {
      if (myAttempt != null) {
        return Row(
          children: [
            Container(padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4), decoration: BoxDecoration(color: Colors.green.withValues(alpha: 0.1), borderRadius: BorderRadius.circular(4)), child: const Text('✓ Submitted', style: TextStyle(fontSize: 12, color: Colors.green))),
            const SizedBox(width: 12),
            TextButton(onPressed: () => context.push('/result/${myAttempt['id']}'), child: const Text('View Answers')),
          ],
        );
      }
      if (locked) return _buildUnlockBtn(minPlan);
      return ElevatedButton(
        onPressed: () => context.push('/exam?testId=$id&mode=mock'),
        style: ElevatedButton.styleFrom(backgroundColor: Colors.green, foregroundColor: Colors.white, minimumSize: const Size(120, 36)),
        child: const Text('Start Mock →', style: TextStyle(fontSize: 13)),
      );
    } else if (status == 'grading') {
      return Text('Window closed. Rank & solutions reveal soon.', style: TextStyle(fontSize: 12, color: Colors.grey[600]));
    } else {
      // Results
      if (myAttempt != null) {
        return ElevatedButton(
          onPressed: () => context.push('/result/${myAttempt['id']}'),
          style: ElevatedButton.styleFrom(backgroundColor: const Color(0xFF4361EE), foregroundColor: Colors.white, minimumSize: const Size(double.infinity, 36)),
          child: const Text('View Rank & Solutions →', style: TextStyle(fontSize: 13)),
        );
      }
      return Text("You didn't take this mock.", style: TextStyle(fontSize: 12, color: Colors.grey[600]));
    }
  }

  Widget _buildUnlockBtn(String minPlan) {
    return ElevatedButton.icon(
      onPressed: () => context.push('/subscription'),
      icon: const Icon(Icons.lock, size: 14),
      label: Text('Unlock with ${minPlan.substring(0,1).toUpperCase()}${minPlan.substring(1)}', style: const TextStyle(fontSize: 12)),
      style: ElevatedButton.styleFrom(backgroundColor: const Color(0xFF4361EE), foregroundColor: Colors.white, minimumSize: const Size(140, 36)),
    );
  }

  String _formatDate(String isoString) {
    final dt = DateTime.parse(isoString).toLocal();
    final months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
    final hour = dt.hour % 12 == 0 ? 12 : dt.hour % 12;
    final ampm = dt.hour >= 12 ? 'PM' : 'AM';
    final min = dt.minute.toString().padLeft(2, '0');
    return '${dt.day} ${months[dt.month-1]} · $hour:$min $ampm';
  }
}
