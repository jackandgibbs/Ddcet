import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:flutter_math_fork/flutter_math.dart';
import 'dart:async';
import '../services/exam_service.dart';

class ExamScreen extends StatefulWidget {
  final int testId;
  final String mode;

  const ExamScreen({super.key, required this.testId, required this.mode});

  @override
  State<ExamScreen> createState() => _ExamScreenState();
}

class _ExamScreenState extends State<ExamScreen> {
  bool _isLoading = true;
  String _errorMessage = '';
  
  Map<String, dynamic>? _attempt;
  List<Map<String, dynamic>> _questions = [];
  int _currentQuestionIndex = 0;
  
  Timer? _timer;
  int _totalSeconds = 0;
  int _secondsRemaining = 0;

  @override
  void initState() {
    super.initState();
    _initExam();
  }

  @override
  void dispose() {
    _timer?.cancel();
    super.dispose();
  }

  Future<void> _initExam() async {
    try {
      final data = await ExamService.generateExam(widget.testId, widget.mode);
      
      if (mounted) {
        setState(() {
          _attempt = data['attempt'];
          _questions = List<Map<String, dynamic>>.from(data['questions']);
          
          _totalSeconds = (_attempt!['duration_minutes'] as num).toInt() * 60;
          
          // Calculate remaining time based on started_at
          final startedAt = DateTime.parse(_attempt!['started_at']).toLocal();
          final elapsed = DateTime.now().difference(startedAt).inSeconds;
          _secondsRemaining = _totalSeconds - elapsed;
          if (_secondsRemaining < 0) _secondsRemaining = 0;
          
          _isLoading = false;
        });
        
        _startTimer();
      }
    } catch (e) {
      if (mounted) {
        setState(() {
          _errorMessage = e.toString().replaceAll('Exception: ', '');
          _isLoading = false;
        });
      }
    }
  }

  void _startTimer() {
    _timer = Timer.periodic(const Duration(seconds: 1), (timer) {
      if (_secondsRemaining > 0) {
        setState(() => _secondsRemaining--);
      } else {
        _timer?.cancel();
        _submitExam(autoSubmit: true);
      }
    });
  }

  void _saveCurrentAnswer() {
    final q = _questions[_currentQuestionIndex];
    ExamService.saveAnswer(
      attemptId: _attempt!['id'],
      questionId: q['id'],
      optionId: q['selected_option_id'],
      isFlagged: q['is_flagged'] == true,
      timeSpent: 10, // simplified time tracking per question
    );
  }

  void _submitExam({bool autoSubmit = false}) async {
    if (_timer != null) _timer!.cancel();
    
    if (!autoSubmit) {
      // Show loading
      showDialog(
        context: context,
        barrierDismissible: false,
        builder: (c) => const Center(child: CircularProgressIndicator()),
      );
    }
    
    // Save last answer just in case
    _saveCurrentAnswer();

    try {
      final timeSpent = _totalSeconds - _secondsRemaining;
      await ExamService.submitExam(_attempt!['id'], timeSpent);
      
      if (mounted) {
        if (!autoSubmit) Navigator.pop(context); // pop loading
        context.go('/result/${_attempt!['id']}');
      }
    } catch (e) {
      if (mounted) {
        if (!autoSubmit) Navigator.pop(context);
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('Error: $e')));
      }
    }
  }

  String get _formattedTime {
    int h = _secondsRemaining ~/ 3600;
    int m = (_secondsRemaining % 3600) ~/ 60;
    int s = _secondsRemaining % 60;
    if (h > 0) return '${h.toString().padLeft(2, '0')}:${m.toString().padLeft(2, '0')}:${s.toString().padLeft(2, '0')}';
    return '${m.toString().padLeft(2, '0')}:${s.toString().padLeft(2, '0')}';
  }

  @override
  Widget build(BuildContext context) {
    if (_isLoading) {
      return const Scaffold(body: Center(child: CircularProgressIndicator()));
    }
    
    if (_errorMessage.isNotEmpty) {
      return Scaffold(
        appBar: AppBar(title: const Text('Error')),
        body: Center(
          child: Padding(
            padding: const EdgeInsets.all(16.0),
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                const Icon(Icons.error_outline, size: 48, color: Colors.red),
                const SizedBox(height: 16),
                Text(_errorMessage, style: const TextStyle(fontSize: 16), textAlign: TextAlign.center),
                const SizedBox(height: 24),
                ElevatedButton(
                  onPressed: () => context.go('/dashboard'),
                  child: const Text('Go Back'),
                )
              ],
            ),
          ),
        ),
      );
    }

    final currentQ = _questions[_currentQuestionIndex];

    return Scaffold(
      backgroundColor: const Color(0xFFF8F9FA),
      appBar: AppBar(
        title: Row(
          children: [
            const Icon(Icons.timer, color: Colors.red),
            const SizedBox(width: 8),
            Text(
              _formattedTime,
              style: const TextStyle(fontWeight: FontWeight.bold, color: Colors.red),
            ),
          ],
        ),
        actions: [
          IconButton(
            icon: Icon(
              currentQ['is_flagged'] == true ? Icons.bookmark : Icons.bookmark_border,
              color: currentQ['is_flagged'] == true ? const Color(0xFF8B5CF6) : null
            ),
            tooltip: 'Mark for Review',
            onPressed: () {
              setState(() {
                currentQ['is_flagged'] = !(currentQ['is_flagged'] == true);
                if (currentQ['status'] == 'unseen') currentQ['status'] = 'skipped';
              });
              _saveCurrentAnswer();
            },
          ),
          IconButton(
            icon: const Icon(Icons.report_problem_outlined),
            tooltip: 'Report Error',
            onPressed: () {
              ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Error reported to admins.')));
            },
          ),
          TextButton(
            onPressed: () {
              showDialog(
                context: context,
                builder: (c) => AlertDialog(
                  title: const Text('Submit Exam?'),
                  content: const Text('Are you sure you want to submit your test? You cannot return to this attempt.'),
                  actions: [
                    TextButton(onPressed: () => Navigator.pop(c), child: const Text('Cancel')),
                    ElevatedButton(
                      style: ElevatedButton.styleFrom(backgroundColor: const Color(0xFF4361EE), foregroundColor: Colors.white),
                      onPressed: () {
                        Navigator.pop(c);
                        _submitExam();
                      }, 
                      child: const Text('Submit Test')
                    ),
                  ],
                ),
              );
            },
            child: const Text('Submit', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16)),
          ),
        ],
      ),
      endDrawer: _buildQuestionPalette(),
      body: Column(
        children: [
          LinearProgressIndicator(
            value: _questions.isEmpty ? 0 : (_questions.where((q) => q['selected_option_id'] != null).length / _questions.length),
            backgroundColor: Colors.grey[200],
            valueColor: const AlwaysStoppedAnimation<Color>(Color(0xFF4361EE)),
          ),
          
          Expanded(
            child: _buildQuestionArea(currentQ),
          ),
          
          _buildBottomBar(currentQ),
        ],
      ),
    );
  }

  Widget _buildQuestionPalette() {
    return Drawer(
      child: SafeArea(
        child: Column(
          children: [
            const Padding(
              padding: EdgeInsets.all(16.0),
              child: Text('Question Palette', style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
            ),
            const Divider(height: 1),
            Expanded(
              child: GridView.builder(
                padding: const EdgeInsets.all(16),
                gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                  crossAxisCount: 5,
                  crossAxisSpacing: 8,
                  mainAxisSpacing: 8,
                ),
                itemCount: _questions.length,
                itemBuilder: (context, index) {
                  final q = _questions[index];
                  bool isAnswered = q['selected_option_id'] != null;
                  bool isFlagged = q['is_flagged'] == true;
                  bool isSkipped = q['status'] == 'skipped';
                  bool isCurrent = _currentQuestionIndex == index;
                  
                  Color bgColor = Colors.white;
                  Color borderColor = const Color(0xFFE2E8F0);
                  Color textColor = Colors.black87;
                  
                  if (isCurrent) {
                    borderColor = const Color(0xFF4361EE);
                    bgColor = const Color(0xFFEFF6FF);
                  } else if (isFlagged) {
                    bgColor = const Color(0xFF8B5CF6);
                    borderColor = const Color(0xFF8B5CF6);
                    textColor = Colors.white;
                  } else if (isAnswered) {
                    bgColor = const Color(0xFF10B981);
                    borderColor = const Color(0xFF10B981);
                    textColor = Colors.white;
                  } else if (isSkipped) {
                    bgColor = const Color(0xFFFEE2E2);
                    borderColor = const Color(0xFFEF4444);
                    textColor = const Color(0xFFDC2626);
                  }

                  return InkWell(
                    onTap: () {
                      _saveCurrentAnswer();
                      setState(() {
                        if (q['status'] == 'unseen') q['status'] = 'skipped';
                        _currentQuestionIndex = index;
                      });
                      Navigator.pop(context);
                    },
                    child: Container(
                      alignment: Alignment.center,
                      decoration: BoxDecoration(
                        color: bgColor,
                        borderRadius: BorderRadius.circular(8),
                        border: Border.all(color: borderColor, width: 2),
                      ),
                      child: Text('${index + 1}', style: TextStyle(color: textColor, fontWeight: FontWeight.bold)),
                    ),
                  );
                },
              ),
            ),
            Padding(
              padding: const EdgeInsets.all(16.0),
              child: Row(
                mainAxisAlignment: MainAxisAlignment.spaceEvenly,
                children: [
                  _legendItem(const Color(0xFF10B981), 'Answered'),
                  _legendItem(const Color(0xFF8B5CF6), 'Marked'),
                  _legendItem(const Color(0xFFFEE2E2), 'Skipped', borderColor: const Color(0xFFEF4444)),
                ],
              ),
            )
          ],
        ),
      ),
    );
  }

  Widget _legendItem(Color color, String label, {Color? borderColor}) {
    return Row(
      children: [
        Container(
          width: 12, height: 12,
          decoration: BoxDecoration(
            color: color,
            borderRadius: BorderRadius.circular(3),
            border: Border.all(color: borderColor ?? color),
          ),
        ),
        const SizedBox(width: 4),
        Text(label, style: const TextStyle(fontSize: 10, color: Colors.black54)),
      ],
    );
  }

  Widget _parseKaTeX(String text) {
    List<Widget> parsedText = [];
    final split = text.split(RegExp(r'(\\\(.*?\\\)|\$\$.*?\$\$)'));
    final matches = RegExp(r'(\\\(.*?\\\)|\$\$.*?\$\$)').allMatches(text).toList();
    
    for (int i = 0; i < split.length; i++) {
      if (split[i].isNotEmpty) {
        parsedText.add(Text(split[i], style: const TextStyle(fontSize: 16, height: 1.5)));
      }
      if (i < matches.length) {
        String mathContent = matches[i].group(0)!;
        mathContent = mathContent.replaceAll(RegExp(r'^\\\(|\\\)$|^\$\$|\$\$$'), '');
        parsedText.add(
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 4.0),
            child: Math.tex(
              mathContent,
              textStyle: const TextStyle(fontSize: 16),
            ),
          )
        );
      }
    }
    return Wrap(
      crossAxisAlignment: WrapCrossAlignment.center,
      children: parsedText,
    );
  }

  Widget _buildQuestionArea(Map<String, dynamic> q) {
    return SingleChildScrollView(
      padding: const EdgeInsets.all(20.0),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Text('Question ${_currentQuestionIndex + 1} of ${_questions.length}', style: const TextStyle(fontSize: 14, color: Colors.grey, fontWeight: FontWeight.bold)),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                decoration: BoxDecoration(color: const Color(0xFFEFF6FF), borderRadius: BorderRadius.circular(4)),
                child: Text('+${q['marks']} / -${q['negative']}', style: const TextStyle(fontSize: 12, color: Color(0xFF4361EE), fontWeight: FontWeight.bold)),
              )
            ],
          ),
          const SizedBox(height: 16),
          
          if (q['text'] != null && q['text'].isNotEmpty)
            _parseKaTeX(q['text']),
            
          if (q['image'] != null && q['image'].isNotEmpty)
            Padding(
              padding: const EdgeInsets.only(top: 16.0),
              child: ClipRRect(
                borderRadius: BorderRadius.circular(8),
                child: Image.network(
                  q['image'].startsWith('http') ? q['image'] : 'https://ddcetprep.onrender.com/${q['image']}', 
                  fit: BoxFit.contain
                ),
              ),
            ),
            
          const SizedBox(height: 32),
          
          ...((q['options'] as List<dynamic>).map((opt) {
            bool isSelected = q['selected_option_id'] == opt['id'];
            return Padding(
              padding: const EdgeInsets.only(bottom: 12.0),
              child: InkWell(
                onTap: () {
                  setState(() {
                    q['selected_option_id'] = opt['id'];
                    q['status'] = 'answered';
                    if (q['is_flagged'] == true) q['is_flagged'] = false; // Auto unflag on answer
                  });
                  _saveCurrentAnswer();
                },
                borderRadius: BorderRadius.circular(12),
                child: Container(
                  padding: const EdgeInsets.all(16),
                  decoration: BoxDecoration(
                    color: isSelected ? const Color(0xFFEFF6FF) : Colors.white,
                    borderRadius: BorderRadius.circular(12),
                    border: Border.all(
                      color: isSelected ? const Color(0xFF4361EE) : const Color(0xFFE2E8F0),
                      width: isSelected ? 2 : 1,
                    ),
                  ),
                  child: Row(
                    children: [
                      Container(
                        width: 24, height: 24,
                        decoration: BoxDecoration(
                          shape: BoxShape.circle,
                          color: isSelected ? const Color(0xFF4361EE) : Colors.transparent,
                          border: Border.all(color: isSelected ? const Color(0xFF4361EE) : Colors.grey),
                        ),
                        child: isSelected 
                            ? const Icon(Icons.check, size: 16, color: Colors.white)
                            : null,
                      ),
                      const SizedBox(width: 16),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            if (opt['text'] != null && opt['text'].isNotEmpty)
                              _parseKaTeX(opt['text']),
                            if (opt['image'] != null && opt['image'].isNotEmpty)
                              Padding(
                                padding: const EdgeInsets.only(top: 8.0),
                                child: Image.network(
                                  opt['image'].startsWith('http') ? opt['image'] : 'https://ddcetprep.onrender.com/${opt['image']}', 
                                  height: 80, fit: BoxFit.contain
                                ),
                              ),
                          ],
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            );
          }).toList()),
        ],
      ),
    );
  }

  Widget _buildBottomBar(Map<String, dynamic> q) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        boxShadow: [
          BoxShadow(color: Colors.black.withOpacity(0.05), blurRadius: 10, offset: const Offset(0, -5))
        ],
      ),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Row(
            children: [
              OutlinedButton.icon(
                icon: const Icon(Icons.clear, size: 16),
                label: const Text('Clear'),
                style: OutlinedButton.styleFrom(
                  foregroundColor: Colors.grey[700],
                  padding: const EdgeInsets.symmetric(horizontal: 12),
                ),
                onPressed: () {
                  setState(() {
                    q['selected_option_id'] = null;
                    q['status'] = q['is_flagged'] == true ? 'unseen' : 'skipped';
                  });
                  _saveCurrentAnswer();
                },
              ),
              const SizedBox(width: 8),
              OutlinedButton.icon(
                icon: const Icon(Icons.auto_awesome, size: 16),
                label: const Text('Ask AI'),
                style: OutlinedButton.styleFrom(
                  foregroundColor: const Color(0xFF8B5CF6),
                  side: const BorderSide(color: Color(0xFFD8B4FE)),
                  padding: const EdgeInsets.symmetric(horizontal: 12),
                ),
                onPressed: () {
                  ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('AI Tutor is analyzing this question...')));
                },
              ),
            ],
          ),
          Row(
            children: [
              if (_currentQuestionIndex > 0)
                IconButton(
                  icon: const Icon(Icons.chevron_left),
                  onPressed: () {
                    _saveCurrentAnswer();
                    setState(() {
                      if (q['status'] == 'unseen') q['status'] = 'skipped';
                      _currentQuestionIndex--;
                    });
                  },
                ),
              ElevatedButton(
                style: ElevatedButton.styleFrom(
                  backgroundColor: const Color(0xFF4361EE),
                  foregroundColor: Colors.white,
                  padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 12),
                ),
                onPressed: () {
                  _saveCurrentAnswer();
                  setState(() {
                    if (q['status'] == 'unseen') q['status'] = 'skipped';
                  });
                  if (_currentQuestionIndex < _questions.length - 1) {
                    setState(() => _currentQuestionIndex++);
                  } else {
                    // Open drawer on last question
                    Scaffold.of(context).openEndDrawer();
                  }
                },
                child: Text(_currentQuestionIndex < _questions.length - 1 ? 'Save & Next' : 'Review'),
              ),
            ],
          ),
        ],
      ),
    );
  }
}
