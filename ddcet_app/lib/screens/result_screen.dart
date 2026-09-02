import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:flutter_math_fork/flutter_math.dart';
import 'dart:convert';
import 'package:http/http.dart' as http;
import '../services/student_service.dart';

class ResultScreen extends StatefulWidget {
  final int attemptId;
  const ResultScreen({super.key, required this.attemptId});

  @override
  State<ResultScreen> createState() => _ResultScreenState();
}

class _ResultScreenState extends State<ResultScreen> {
  bool _isLoading = true;
  String _error = '';
  Map<String, dynamic>? _resultData;

  @override
  void initState() {
    super.initState();
    _fetchResult();
  }

  Future<void> _fetchResult() async {
    final studentId = await StudentService.getStudentId();
    if (studentId == null) {
      if (mounted) setState(() { _error = 'Not logged in'; _isLoading = false; });
      return;
    }

    try {
      final res = await http.get(
        Uri.parse('https://ddcetprep.onrender.com/api/get_result.php?attempt_id=${widget.attemptId}'),
        headers: {'X-Student-Id': studentId.toString()},
      );
      
      final data = jsonDecode(res.body);
      if (data['error'] != null) {
        throw Exception(data['error']);
      }
      
      if (mounted) {
        setState(() {
          _resultData = data;
          _isLoading = false;
        });
      }
    } catch (e) {
      if (mounted) setState(() { _error = e.toString(); _isLoading = false; });
    }
  }

  Widget _parseKaTeX(String text) {
    List<Widget> parsedText = [];
    final split = text.split(RegExp(r'(\\\(.*?\\\)|\$\$.*?\$\$)'));
    final matches = RegExp(r'(\\\(.*?\\\)|\$\$.*?\$\$)').allMatches(text).toList();
    
    for (int i = 0; i < split.length; i++) {
      if (split[i].isNotEmpty) {
        parsedText.add(Text(split[i], style: const TextStyle(fontSize: 15, height: 1.5)));
      }
      if (i < matches.length) {
        String mathContent = matches[i].group(0)!;
        mathContent = mathContent.replaceAll(RegExp(r'^\\\(|\\\)$|^\$\$|\$\$$'), '');
        parsedText.add(
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 4.0),
            child: Math.tex(mathContent, textStyle: const TextStyle(fontSize: 15)),
          )
        );
      }
    }
    return Wrap(crossAxisAlignment: WrapCrossAlignment.center, children: parsedText);
  }

  @override
  Widget build(BuildContext context) {
    if (_isLoading) return const Scaffold(body: Center(child: CircularProgressIndicator()));
    if (_error.isNotEmpty) return Scaffold(appBar: AppBar(title: const Text('Result')), body: Center(child: Text(_error)));

    final attempt = _resultData!['attempt'];
    final answers = _resultData!['answers'] as List<dynamic>? ?? [];
    final air = _resultData!['air'];

    return Scaffold(
      backgroundColor: const Color(0xFFF8F9FA),
      appBar: AppBar(
        title: Text(attempt['title'] ?? 'Result'),
        leading: IconButton(
          icon: const Icon(Icons.arrow_back),
          onPressed: () => context.go('/dashboard'),
        ),
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            // Score Card
            Container(
              padding: const EdgeInsets.all(24),
              decoration: BoxDecoration(
                gradient: const LinearGradient(colors: [Color(0xFF4361EE), Color(0xFF3A0CA3)]),
                borderRadius: BorderRadius.circular(16),
                boxShadow: [BoxShadow(color: const Color(0xFF4361EE).withOpacity(0.3), blurRadius: 10, offset: const Offset(0, 5))],
              ),
              child: Column(
                children: [
                  const Text('YOUR SCORE', style: TextStyle(color: Colors.white70, letterSpacing: 1.2, fontWeight: FontWeight.bold)),
                  const SizedBox(height: 8),
                  Text('${attempt['score']} / ${attempt['total_marks']}', style: const TextStyle(fontSize: 48, fontWeight: FontWeight.bold, color: Colors.white)),
                  const SizedBox(height: 16),
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceEvenly,
                    children: [
                      _statCol('Accuracy', '${_resultData!['score_percent']}%'),
                      _statCol('Time / Q', '${_resultData!['avg_time_per_q']}s'),
                      _statCol('XP Gained', '+${attempt['xp_awarded'] ?? 0}'),
                    ],
                  ),
                ],
              ),
            ),
            
            if (air != null)
              Container(
                margin: const EdgeInsets.only(top: 16),
                padding: const EdgeInsets.all(16),
                decoration: BoxDecoration(
                  gradient: const LinearGradient(colors: [Color(0xFF1a1a2e), Color(0xFF2d1b69)]),
                  borderRadius: BorderRadius.circular(12),
                ),
                child: Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const Text('ALL-INDIA RANK', style: TextStyle(color: Colors.white70, fontSize: 12, letterSpacing: 1)),
                        Text('#${air['rank']}', style: const TextStyle(color: Colors.white, fontSize: 32, fontWeight: FontWeight.bold)),
                        Text('out of ${air['total']} · ${air['percentile']} percentile', style: const TextStyle(color: Colors.white70, fontSize: 12)),
                      ],
                    ),
                    const Icon(Icons.emoji_events, color: Colors.amber, size: 48),
                  ],
                ),
              ),

            const SizedBox(height: 24),
            const Text('Detailed Solutions', style: TextStyle(fontSize: 20, fontWeight: FontWeight.bold)),
            const SizedBox(height: 12),

            if (_resultData!['solutions_locked'] == true)
              Container(
                padding: const EdgeInsets.all(20),
                decoration: BoxDecoration(
                  color: const Color(0xFFFFFBEB),
                  borderRadius: BorderRadius.circular(12),
                  border: Border.all(color: Colors.amber.shade200),
                ),
                child: Column(
                  children: [
                    const Icon(Icons.lock, color: Colors.amber, size: 48),
                    const SizedBox(height: 12),
                    const Text('Detailed solutions are locked.', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16)),
                    const SizedBox(height: 4),
                    const Text('Get the Pro plan to access detailed solutions, exact correct answers, and AI explanations for all scheduled mocks.', textAlign: TextAlign.center, style: TextStyle(color: Colors.black54)),
                    const SizedBox(height: 16),
                    ElevatedButton(
                      style: ElevatedButton.styleFrom(backgroundColor: Colors.amber.shade600, foregroundColor: Colors.white),
                      onPressed: () => context.push('/subscription'),
                      child: const Text('Upgrade to Pro'),
                    )
                  ],
                ),
              )
            else
              ...answers.asMap().entries.map((entry) {
                int index = entry.key;
                var a = entry.value;
                bool isCorrect = a['is_correct'] == true;
                bool isSkipped = a['selected_option_id'] == null;
                
                Color statusColor = isSkipped ? Colors.grey : (isCorrect ? const Color(0xFF10B981) : const Color(0xFFEF4444));
                IconData statusIcon = isSkipped ? Icons.remove_circle_outline : (isCorrect ? Icons.check_circle : Icons.cancel);

                return Container(
                  margin: const EdgeInsets.only(bottom: 16),
                  decoration: BoxDecoration(
                    color: Colors.white,
                    borderRadius: BorderRadius.circular(12),
                    border: Border.all(color: Colors.grey.shade200),
                    boxShadow: [BoxShadow(color: Colors.black.withOpacity(0.02), blurRadius: 4, offset: const Offset(0, 2))],
                  ),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Container(
                        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
                        decoration: BoxDecoration(
                          color: statusColor.withOpacity(0.1),
                          borderRadius: const BorderRadius.vertical(top: Radius.circular(12)),
                          border: Border(bottom: BorderSide(color: statusColor.withOpacity(0.2))),
                        ),
                        child: Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            Text('Question ${index + 1}', style: TextStyle(fontWeight: FontWeight.bold, color: statusColor)),
                            Row(
                              children: [
                                Icon(statusIcon, color: statusColor, size: 16),
                                const SizedBox(width: 4),
                                Text(isSkipped ? 'Skipped' : (isCorrect ? '+${a['marks']}' : '-${a['negative_marks']}'), style: TextStyle(color: statusColor, fontWeight: FontWeight.bold)),
                              ],
                            )
                          ],
                        ),
                      ),
                      Padding(
                        padding: const EdgeInsets.all(16),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            if (a['question_text'] != null) _parseKaTeX(a['question_text']),
                            if (a['question_image'] != null && a['question_image'].toString().isNotEmpty)
                              Padding(
                                padding: const EdgeInsets.only(top: 12),
                                child: Image.network('https://ddcetprep.onrender.com/${a['question_image']}'),
                              ),
                            const SizedBox(height: 20),
                            
                            ...((a['options'] as List<dynamic>).map((opt) {
                              bool isSelected = opt['id'] == a['selected_option_id'];
                              bool isTrueCorrect = opt['is_correct'] == true;
                              
                              Color bg = Colors.transparent;
                              Color border = Colors.grey.shade300;
                              
                              if (isTrueCorrect) {
                                bg = const Color(0xFF10B981).withOpacity(0.1);
                                border = const Color(0xFF10B981);
                              } else if (isSelected && !isTrueCorrect) {
                                bg = const Color(0xFFEF4444).withOpacity(0.1);
                                border = const Color(0xFFEF4444);
                              }
                              
                              return Container(
                                margin: const EdgeInsets.only(bottom: 8),
                                padding: const EdgeInsets.all(12),
                                decoration: BoxDecoration(
                                  color: bg,
                                  borderRadius: BorderRadius.circular(8),
                                  border: Border.all(color: border),
                                ),
                                child: Row(
                                  children: [
                                    if (isTrueCorrect)
                                      const Icon(Icons.check_circle, color: Color(0xFF10B981), size: 20)
                                    else if (isSelected)
                                      const Icon(Icons.cancel, color: Color(0xFFEF4444), size: 20)
                                    else
                                      const SizedBox(width: 20),
                                      
                                    const SizedBox(width: 12),
                                    Expanded(child: _parseKaTeX(opt['text'] ?? '')),
                                  ],
                                ),
                              );
                            })),
                            
                            if (a['explanation'] != null && a['explanation'].toString().isNotEmpty) ...[
                              const SizedBox(height: 16),
                              Container(
                                padding: const EdgeInsets.all(16),
                                decoration: BoxDecoration(
                                  color: const Color(0xFFEFF6FF),
                                  borderRadius: BorderRadius.circular(8),
                                  border: Border.all(color: const Color(0xFFBFDBFE)),
                                ),
                                child: Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    const Row(
                                      children: [
                                        Icon(Icons.lightbulb, color: Colors.amber, size: 16),
                                        SizedBox(width: 8),
                                        Text('Explanation', style: TextStyle(fontWeight: FontWeight.bold, color: Color(0xFF1E3A8A))),
                                      ],
                                    ),
                                    const SizedBox(height: 8),
                                    _parseKaTeX(a['explanation']),
                                  ],
                                ),
                              )
                            ]
                          ],
                        ),
                      )
                    ],
                  ),
                );
              }),
          ],
        ),
      ),
    );
  }

  Widget _statCol(String label, String val) {
    return Column(
      children: [
        Text(val, style: const TextStyle(color: Colors.white, fontSize: 20, fontWeight: FontWeight.bold)),
        Text(label, style: const TextStyle(color: Colors.white70, fontSize: 12)),
      ],
    );
  }
}
