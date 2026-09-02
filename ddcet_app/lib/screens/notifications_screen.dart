import 'package:flutter/material.dart';
import 'package:supabase_flutter/supabase_flutter.dart';
import 'package:go_router/go_router.dart';
import '../services/student_service.dart';

class NotificationsScreen extends StatefulWidget {
  const NotificationsScreen({super.key});

  @override
  State<NotificationsScreen> createState() => _NotificationsScreenState();
}

class _NotificationsScreenState extends State<NotificationsScreen> {
  bool _isLoading = true;
  List<Map<String, dynamic>> _notifs = [];

  @override
  void initState() {
    super.initState();
    _fetchNotifications();
  }

  Future<void> _fetchNotifications() async {
    final studentId = await StudentService.getStudentId();
    if (studentId == null) {
      if (mounted) setState(() => _isLoading = false);
      return;
    }

    try {
      // Mark all as read
      await Supabase.instance.client
          .from('notifications')
          .update({'is_read': true})
          .eq('student_id', studentId)
          .eq('is_read', false);

      final rows = await Supabase.instance.client
          .from('notifications')
          .select('*')
          .eq('student_id', studentId)
          .order('created_at', ascending: false)
          .limit(50);

      _notifs = List<Map<String, dynamic>>.from(rows);
      if (mounted) setState(() => _isLoading = false);
    } catch (e) {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  IconData _iconForType(String? type) {
    switch (type) {
      case 'badge': return Icons.emoji_events;
      case 'level_up': return Icons.arrow_upward;
      case 'xp': return Icons.star;
      case 'friend': return Icons.person_add;
      case 'challenge': return Icons.sports_martial_arts;
      case 'streak': return Icons.local_fire_department;
      default: return Icons.notifications;
    }
  }

  Color _colorForType(String? type) {
    switch (type) {
      case 'badge': return const Color(0xFFF59E0B);
      case 'level_up': return const Color(0xFF8B5CF6);
      case 'xp': return const Color(0xFF10B981);
      case 'friend': return const Color(0xFF3B82F6);
      case 'challenge': return const Color(0xFFEF4444);
      case 'streak': return const Color(0xFFF97316);
      default: return const Color(0xFF4361EE);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFF8F9FA),
      appBar: AppBar(
        title: const Text('Notifications', style: TextStyle(fontWeight: FontWeight.bold)),
        leading: IconButton(icon: const Icon(Icons.arrow_back), onPressed: () => context.go('/dashboard')),
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator())
          : _notifs.isEmpty
              ? Center(
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Icon(Icons.notifications_none, size: 64, color: Colors.grey[400]),
                      const SizedBox(height: 16),
                      const Text('No notifications', style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
                    ],
                  ),
                )
              : ListView.builder(
                  padding: const EdgeInsets.all(16),
                  itemCount: _notifs.length,
                  itemBuilder: (context, index) {
                    final n = _notifs[index];
                    final date = n['created_at'] != null ? DateTime.tryParse(n['created_at']) : null;
                    final ago = date != null ? _timeAgo(date) : '';
                    final color = _colorForType(n['type']);

                    return Container(
                      margin: const EdgeInsets.only(bottom: 8),
                      padding: const EdgeInsets.all(14),
                      decoration: BoxDecoration(
                        color: Colors.white,
                        borderRadius: BorderRadius.circular(12),
                        border: Border.all(color: const Color(0xFFE2E8F0)),
                      ),
                      child: Row(
                        children: [
                          Container(
                            width: 40, height: 40,
                            decoration: BoxDecoration(
                              color: color.withValues(alpha: 0.1),
                              borderRadius: BorderRadius.circular(10),
                            ),
                            child: Icon(_iconForType(n['type']), color: color, size: 20),
                          ),
                          const SizedBox(width: 12),
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(n['title'] ?? 'Notification', style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 14)),
                                if (n['body'] != null)
                                  Text(n['body'], style: TextStyle(fontSize: 12, color: Colors.grey[600])),
                              ],
                            ),
                          ),
                          Text(ago, style: TextStyle(fontSize: 11, color: Colors.grey[400])),
                        ],
                      ),
                    );
                  },
                ),
    );
  }

  String _timeAgo(DateTime dt) {
    final diff = DateTime.now().difference(dt);
    if (diff.inMinutes < 60) return '${diff.inMinutes}m ago';
    if (diff.inHours < 24) return '${diff.inHours}h ago';
    if (diff.inDays < 7) return '${diff.inDays}d ago';
    return '${dt.day}/${dt.month}';
  }
}
