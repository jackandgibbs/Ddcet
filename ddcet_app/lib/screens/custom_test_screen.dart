import 'package:flutter/material.dart';
import 'package:supabase_flutter/supabase_flutter.dart';
import 'package:go_router/go_router.dart';
import 'package:razorpay_flutter/razorpay_flutter.dart';
import 'package:flutter_dotenv/flutter_dotenv.dart';
import '../services/student_service.dart';

class CustomTestScreen extends StatefulWidget {
  const CustomTestScreen({super.key});

  @override
  State<CustomTestScreen> createState() => _CustomTestScreenState();
}

class _CustomTestScreenState extends State<CustomTestScreen> {
  bool _isLoading = true;
  bool _isGenerating = false;
  bool _isSubscribed = false;
  
  Map<String, int> _subjects = {};
  final Set<String> _selectedSubjects = {};
  String _difficulty = 'all';
  double _questionCount = 20;

  late Razorpay _razorpay;

  @override
  void initState() {
    super.initState();
    _fetchData();
    _razorpay = Razorpay();
    _razorpay.on(Razorpay.EVENT_PAYMENT_SUCCESS, _handlePaymentSuccess);
    _razorpay.on(Razorpay.EVENT_PAYMENT_ERROR, _handlePaymentError);
    _razorpay.on(Razorpay.EVENT_EXTERNAL_WALLET, _handleExternalWallet);
  }

  @override
  void dispose() {
    _razorpay.clear();
    super.dispose();
  }

  Future<void> _fetchData() async {
    try {
      final studentId = await StudentService.getStudentId();
      if (studentId != null) {
        final subRows = await Supabase.instance.client
            .from('subscriptions')
            .select('id')
            .eq('student_id', studentId)
            .eq('status', 'active')
            .gte('expires_at', DateTime.now().toIso8601String())
            .limit(1);
        _isSubscribed = subRows.isNotEmpty;
      }

      // Fetch subjects from pool (test_id is null)
      final rows = await Supabase.instance.client
          .from('questions')
          .select('subject')
          .isFilter('test_id', null)
          .limit(5000);
          
      Map<String, int> subs = {};
      for (var r in rows) {
        String? s = r['subject'];
        if (s != null && s.isNotEmpty) {
          subs[s] = (subs[s] ?? 0) + 1;
        }
      }
      
      final sortedKeys = subs.keys.toList()..sort();
      Map<String, int> sortedSubs = {for (var k in sortedKeys) k: subs[k]!};

      if (mounted) {
        setState(() {
          _subjects = sortedSubs;
          _selectedSubjects.addAll(sortedSubs.keys);
          _isLoading = false;
        });
      }
    } catch (e) {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  void _handlePaymentSuccess(PaymentSuccessResponse response) async {
    _generateTest(); // Verified by backend in real life, proceed to generate
  }

  void _handlePaymentError(PaymentFailureResponse response) {
    setState(() => _isGenerating = false);
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(content: Text('Payment failed: ${response.message}'), backgroundColor: Colors.red),
    );
  }

  void _handleExternalWallet(ExternalWalletResponse response) {
    setState(() => _isGenerating = false);
  }

  Future<void> _initiatePaymentAndGenerate() async {
    if (_selectedSubjects.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Please select at least one subject'), backgroundColor: Colors.orange),
      );
      return;
    }

    setState(() => _isGenerating = true);

    if (_isSubscribed) {
      await _generateTest();
    } else {
      try {
        final profile = await StudentService.getProfile();
        // Since we can't reliably hit the PHP backend's create_order API without session mapping,
        // we will simulate the integration layer here. In production, this requires an API call 
        // to get a real Razorpay Order ID.
        var options = {
          'key': dotenv.env['RAZORPAY_KEY_ID'] ?? 'rzp_test_dummy',
          'amount': 2900, // 29 INR
          'name': 'DDCET Prep',
          'description': 'Custom Test Generator',
          'prefill': {
            'contact': '',
            'email': profile?['email'] ?? ''
          }
        };
        _razorpay.open(options);
      } catch (e) {
        setState(() => _isGenerating = false);
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Could not start payment'), backgroundColor: Colors.red),
        );
      }
    }
  }

  Future<void> _generateTest() async {
    try {
      final studentId = await StudentService.getStudentId();
      
      var query = Supabase.instance.client
          .from('questions')
          .select('id')
          .isFilter('test_id', null)
          .inFilter('subject', _selectedSubjects.toList())
          .limit(1000);
          
      if (_difficulty != 'all') {
        query = Supabase.instance.client
            .from('questions')
            .select('id')
            .isFilter('test_id', null)
            .inFilter('subject', _selectedSubjects.toList())
            .eq('difficulty', _difficulty)
            .limit(1000);
      }

      final pool = await query;
      
      if (pool.length < 5) {
        _showError('Not enough questions found for your selection. Try broader filters.');
        return;
      }
      
      pool.shuffle();
      final selectedQs = pool.take(_questionCount.toInt()).toList();

      final attemptRes = await Supabase.instance.client.from('attempts').insert({
        'student_id': studentId,
        'mode': 'custom',
        'total_marks': selectedQs.length,
        'status': 'in_progress',
      }).select();
      
      if (attemptRes.isNotEmpty) {
        int attemptId = attemptRes[0]['id'];
        List<Map<String, dynamic>> answers = [];
        for (int i = 0; i < selectedQs.length; i++) {
          answers.add({
            'attempt_id': attemptId,
            'question_id': selectedQs[i]['id'],
            'position': i
          });
        }
        await Supabase.instance.client.from('attempt_answers').insert(answers);
        
        if (mounted) {
          setState(() => _isGenerating = false);
          context.push('/exam?testId=$attemptId&mode=custom');
        }
      } else {
        _showError('Failed to create test attempt.');
      }
    } catch (e) {
      _showError('Error generating test: $e');
    }
  }

  void _showError(String msg) {
    if (mounted) {
      setState(() => _isGenerating = false);
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(msg), backgroundColor: Colors.red),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFF8F9FA),
      appBar: AppBar(
        title: const Text('Custom Test Generator', style: TextStyle(fontWeight: FontWeight.bold)),
        leading: IconButton(icon: const Icon(Icons.arrow_back), onPressed: () => context.go('/dashboard')),
        backgroundColor: Colors.white,
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator())
          : ListView(
              padding: const EdgeInsets.all(16),
              children: [
                // Header pricing card
                Container(
                  padding: const EdgeInsets.all(16),
                  decoration: BoxDecoration(
                    color: Colors.white,
                    borderRadius: BorderRadius.circular(12),
                    border: Border.all(color: const Color(0xFF4361EE)),
                  ),
                  child: Row(
                    children: [
                      const Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text('Custom Test Generator', style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold)),
                            SizedBox(height: 4),
                            Text('Pick your subjects, difficulty, and question count. Get a personalized test instantly.', 
                                style: TextStyle(fontSize: 12, color: Colors.grey)),
                          ],
                        ),
                      ),
                      const SizedBox(width: 12),
                      if (_isSubscribed)
                        Container(
                          padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
                          decoration: BoxDecoration(color: Colors.green.withValues(alpha: 0.1), borderRadius: BorderRadius.circular(8)),
                          child: const Text('Free with Pro', style: TextStyle(color: Colors.green, fontWeight: FontWeight.bold, fontSize: 12)),
                        )
                      else
                        Column(
                          crossAxisAlignment: CrossAxisAlignment.end,
                          children: [
                            const Text('₹29', style: TextStyle(fontFamily: 'monospace', fontSize: 24, fontWeight: FontWeight.bold, color: Color(0xFF4361EE))),
                            Text('per test', style: TextStyle(fontSize: 11, color: Colors.grey[600])),
                          ],
                        )
                    ],
                  ),
                ),
                
                const SizedBox(height: 20),

                // Form
                Container(
                  padding: const EdgeInsets.all(16),
                  decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(12), border: Border.all(color: const Color(0xFFE2E8F0))),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Text('Select Subjects', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16)),
                      const SizedBox(height: 12),
                      Wrap(
                        spacing: 8, runSpacing: 8,
                        children: _subjects.entries.map((e) {
                          final selected = _selectedSubjects.contains(e.key);
                          return FilterChip(
                            label: Text('${e.key} (${e.value})', style: TextStyle(fontSize: 12, color: selected ? Colors.white : Colors.black87)),
                            selected: selected,
                            selectedColor: const Color(0xFF4361EE),
                            backgroundColor: const Color(0xFFF8F9FA),
                            onSelected: (val) {
                              setState(() {
                                if (val) _selectedSubjects.add(e.key);
                                else _selectedSubjects.remove(e.key);
                              });
                            },
                          );
                        }).toList(),
                      ),

                      const SizedBox(height: 24),
                      const Text('Difficulty', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16)),
                      const SizedBox(height: 12),
                      Row(
                        children: ['all', 'easy', 'medium', 'hard'].map((d) {
                          return Expanded(
                            child: Padding(
                              padding: const EdgeInsets.symmetric(horizontal: 4),
                              child: ChoiceChip(
                                label: Text(d.toUpperCase(), style: TextStyle(fontSize: 11, color: _difficulty == d ? Colors.white : Colors.black87)),
                                selected: _difficulty == d,
                                selectedColor: d == 'easy' ? Colors.green : (d == 'medium' ? Colors.orange : (d == 'hard' ? Colors.red : const Color(0xFF4361EE))),
                                onSelected: (v) => setState(() => _difficulty = d),
                              ),
                            ),
                          );
                        }).toList(),
                      ),

                      const SizedBox(height: 24),
                      const Text('Number of Questions', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16)),
                      const SizedBox(height: 12),
                      Row(
                        children: [
                          Expanded(
                            child: Slider(
                              value: _questionCount,
                              min: 5, max: 50, divisions: 45,
                              activeColor: const Color(0xFF4361EE),
                              onChanged: (v) => setState(() => _questionCount = v),
                            ),
                          ),
                          Container(
                            width: 40, alignment: Alignment.center,
                            child: Text(_questionCount.toInt().toString(), style: const TextStyle(fontFamily: 'monospace', fontSize: 20, fontWeight: FontWeight.bold, color: Color(0xFF4361EE))),
                          ),
                        ],
                      ),
                      Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          Text('5 questions', style: TextStyle(fontSize: 11, color: Colors.grey[500])),
                          Text('50 questions', style: TextStyle(fontSize: 11, color: Colors.grey[500])),
                        ],
                      ),

                      const SizedBox(height: 32),
                      SizedBox(
                        width: double.infinity,
                        height: 50,
                        child: ElevatedButton(
                          onPressed: _isGenerating ? null : _initiatePaymentAndGenerate,
                          style: ElevatedButton.styleFrom(
                            backgroundColor: const Color(0xFF4361EE),
                            foregroundColor: Colors.white,
                            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                          ),
                          child: _isGenerating 
                            ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2))
                            : const Text('Generate My Test →', style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold)),
                        ),
                      )
                    ],
                  ),
                ),
              ],
            ),
    );
  }
}
