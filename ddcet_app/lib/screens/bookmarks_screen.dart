import 'package:flutter/material.dart';
import 'package:supabase_flutter/supabase_flutter.dart';
import 'package:go_router/go_router.dart';
import '../services/student_service.dart';

class BookmarksScreen extends StatefulWidget {
  const BookmarksScreen({super.key});

  @override
  State<BookmarksScreen> createState() => _BookmarksScreenState();
}

class _BookmarksScreenState extends State<BookmarksScreen> {
  bool _isLoading = true;
  List<Map<String, dynamic>> _bookmarks = [];

  @override
  void initState() {
    super.initState();
    _fetchBookmarks();
  }

  Future<void> _fetchBookmarks() async {
    final studentId = await StudentService.getStudentId();
    if (studentId == null) {
      if (mounted) setState(() => _isLoading = false);
      return;
    }

    try {
      final rows = await Supabase.instance.client
          .from('bookmarked_questions')
          .select('created_at,question_id,questions(question_text,subject)')
          .eq('student_id', studentId)
          .order('created_at', ascending: false);

      _bookmarks = List<Map<String, dynamic>>.from(rows);

      // Get correct answers
      final qIds = _bookmarks.map((b) => b['question_id']).toList();
      if (qIds.isNotEmpty) {
        final opts = await Supabase.instance.client
            .from('options')
            .select('question_id,option_text')
            .inFilter('question_id', qIds)
            .eq('is_correct', true);

        Map<int, String> correctMap = {};
        for (var o in opts) {
          correctMap[o['question_id'] as int] = o['option_text'] as String;
        }
        for (var b in _bookmarks) {
          b['correct_answer'] = correctMap[b['question_id']] ?? '';
        }
      }

      if (mounted) setState(() => _isLoading = false);
    } catch (e) {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  Future<void> _removeBookmark(int questionId) async {
    final studentId = await StudentService.getStudentId();
    if (studentId == null) return;

    await Supabase.instance.client
        .from('bookmarked_questions')
        .delete()
        .eq('student_id', studentId)
        .eq('question_id', questionId);

    setState(() {
      _bookmarks.removeWhere((b) => b['question_id'] == questionId);
    });
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFF8F9FA),
      appBar: AppBar(
        title: const Text('Bookmarks', style: TextStyle(fontWeight: FontWeight.bold)),
        leading: IconButton(icon: const Icon(Icons.arrow_back), onPressed: () => context.go('/dashboard')),
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator())
          : _bookmarks.isEmpty
              ? Center(
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Icon(Icons.bookmark_border, size: 64, color: Colors.grey[400]),
                      const SizedBox(height: 16),
                      const Text('No bookmarked questions', style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
                      const SizedBox(height: 8),
                      Text('Save questions during tests to revisit later!', style: TextStyle(color: Colors.grey[600])),
                    ],
                  ),
                )
              : ListView.builder(
                  padding: const EdgeInsets.all(16),
                  itemCount: _bookmarks.length + 1,
                  itemBuilder: (context, index) {
                    if (index == 0) {
                      return Padding(
                        padding: const EdgeInsets.only(bottom: 12),
                        child: Text('${_bookmarks.length} saved questions', style: TextStyle(color: Colors.grey[600], fontSize: 12)),
                      );
                    }
                    final b = _bookmarks[index - 1];
                    final q = b['questions'] as Map<String, dynamic>?;
                    return Container(
                      margin: const EdgeInsets.only(bottom: 12),
                      padding: const EdgeInsets.all(14),
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
                              if (q?['subject'] != null)
                                Container(
                                  padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                                  decoration: BoxDecoration(
                                    color: const Color(0xFFEFF6FF),
                                    borderRadius: BorderRadius.circular(4),
                                  ),
                                  child: Text(q!['subject'], style: const TextStyle(fontSize: 10, color: Color(0xFF4361EE), fontWeight: FontWeight.bold)),
                                ),
                              const Spacer(),
                              IconButton(
                                icon: const Icon(Icons.bookmark_remove, color: Colors.red, size: 20),
                                onPressed: () => _removeBookmark(b['question_id'] as int),
                                padding: EdgeInsets.zero,
                                constraints: const BoxConstraints(),
                              ),
                            ],
                          ),
                          const SizedBox(height: 8),
                          Text(q?['question_text'] ?? 'Question', style: const TextStyle(fontSize: 14, height: 1.4)),
                          if (b['correct_answer'] != null && (b['correct_answer'] as String).isNotEmpty) ...[
                            const SizedBox(height: 10),
                            Container(
                              padding: const EdgeInsets.all(10),
                              decoration: BoxDecoration(
                                color: Colors.green.withValues(alpha: 0.08),
                                borderRadius: BorderRadius.circular(8),
                              ),
                              child: Row(
                                children: [
                                  const Icon(Icons.check_circle, color: Colors.green, size: 16),
                                  const SizedBox(width: 8),
                                  Expanded(child: Text(b['correct_answer'], style: const TextStyle(fontSize: 13, color: Colors.green, fontWeight: FontWeight.bold))),
                                ],
                              ),
                            ),
                          ],
                        ],
                      ),
                    );
                  },
                ),
    );
  }
}
