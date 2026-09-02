import 'package:flutter/material.dart';
import 'package:supabase_flutter/supabase_flutter.dart';
import 'package:go_router/go_router.dart';
import '../services/student_service.dart';

class ContactScreen extends StatefulWidget {
  const ContactScreen({super.key});

  @override
  State<ContactScreen> createState() => _ContactScreenState();
}

class _ContactScreenState extends State<ContactScreen> {
  final _subjectController = TextEditingController();
  final _messageController = TextEditingController();
  bool _isSending = false;
  List<Map<String, dynamic>> _pastQueries = [];
  bool _isLoading = true;

  @override
  void initState() {
    super.initState();
    _fetchPastQueries();
  }

  @override
  void dispose() {
    _subjectController.dispose();
    _messageController.dispose();
    super.dispose();
  }

  Future<void> _fetchPastQueries() async {
    final studentId = await StudentService.getStudentId();
    if (studentId == null) {
      if (mounted) setState(() => _isLoading = false);
      return;
    }
    try {
      final rows = await Supabase.instance.client
          .from('queries')
          .select('*')
          .eq('student_id', studentId)
          .order('created_at', ascending: false)
          .limit(20);
      _pastQueries = List<Map<String, dynamic>>.from(rows);
    } catch (_) {}
    if (mounted) setState(() => _isLoading = false);
  }

  Future<void> _submitQuery() async {
    final subject = _subjectController.text.trim();
    final message = _messageController.text.trim();
    if (subject.isEmpty || message.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Please fill in both fields'), backgroundColor: Colors.orange),
      );
      return;
    }

    final studentId = await StudentService.getStudentId();
    if (studentId == null) return;

    setState(() => _isSending = true);
    try {
      await Supabase.instance.client.from('queries').insert({
        'student_id': studentId,
        'subject': subject,
        'message': message,
      });
      _subjectController.clear();
      _messageController.clear();
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Query submitted!'), backgroundColor: Colors.green),
        );
      }
      _fetchPastQueries();
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Error: $e'), backgroundColor: Colors.red),
        );
      }
    }
    if (mounted) setState(() => _isSending = false);
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFF8F9FA),
      appBar: AppBar(
        title: const Text('Contact Us', style: TextStyle(fontWeight: FontWeight.bold)),
        leading: IconButton(icon: const Icon(Icons.arrow_back), onPressed: () => context.go('/dashboard')),
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Container(
              padding: const EdgeInsets.all(16),
              decoration: BoxDecoration(
                color: Colors.white, borderRadius: BorderRadius.circular(12),
                border: Border.all(color: const Color(0xFFE2E8F0)),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Text('Send us a message', style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
                  const SizedBox(height: 16),
                  TextField(
                    controller: _subjectController,
                    decoration: InputDecoration(
                      labelText: 'Subject',
                      border: OutlineInputBorder(borderRadius: BorderRadius.circular(8)),
                      filled: true, fillColor: const Color(0xFFF8F9FA),
                    ),
                  ),
                  const SizedBox(height: 12),
                  TextField(
                    controller: _messageController,
                    maxLines: 4,
                    decoration: InputDecoration(
                      labelText: 'Message',
                      border: OutlineInputBorder(borderRadius: BorderRadius.circular(8)),
                      filled: true, fillColor: const Color(0xFFF8F9FA),
                    ),
                  ),
                  const SizedBox(height: 16),
                  SizedBox(
                    width: double.infinity,
                    child: ElevatedButton(
                      onPressed: _isSending ? null : _submitQuery,
                      style: ElevatedButton.styleFrom(
                        backgroundColor: const Color(0xFF4361EE), foregroundColor: Colors.white,
                        padding: const EdgeInsets.symmetric(vertical: 14),
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                      ),
                      child: _isSending
                          ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2))
                          : const Text('Submit', style: TextStyle(fontWeight: FontWeight.bold)),
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 24),
            if (_pastQueries.isNotEmpty) ...[
              const Text('Past Queries', style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold)),
              const SizedBox(height: 12),
              ..._pastQueries.map((q) {
                final date = q['created_at'] != null ? DateTime.tryParse(q['created_at']) : null;
                return Container(
                  margin: const EdgeInsets.only(bottom: 8),
                  padding: const EdgeInsets.all(12),
                  decoration: BoxDecoration(
                    color: Colors.white, borderRadius: BorderRadius.circular(10),
                    border: Border.all(color: const Color(0xFFE2E8F0)),
                  ),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        children: [
                          Expanded(child: Text(q['subject'] ?? '', style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 14))),
                          if (date != null) Text('${date.day}/${date.month}', style: TextStyle(fontSize: 11, color: Colors.grey[400])),
                        ],
                      ),
                      const SizedBox(height: 4),
                      Text(q['message'] ?? '', style: TextStyle(fontSize: 13, color: Colors.grey[700])),
                      if (q['reply'] != null) ...[
                        const SizedBox(height: 8),
                        Container(
                          padding: const EdgeInsets.all(8),
                          decoration: BoxDecoration(
                            color: const Color(0xFFEFF6FF), borderRadius: BorderRadius.circular(6),
                          ),
                          child: Row(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              const Icon(Icons.reply, size: 14, color: Color(0xFF4361EE)),
                              const SizedBox(width: 6),
                              Expanded(child: Text(q['reply'], style: const TextStyle(fontSize: 12, color: Color(0xFF4361EE)))),
                            ],
                          ),
                        ),
                      ],
                    ],
                  ),
                );
              }),
            ],
          ],
        ),
      ),
    );
  }
}
