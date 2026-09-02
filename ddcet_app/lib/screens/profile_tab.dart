import 'package:flutter/material.dart';
import 'package:supabase_flutter/supabase_flutter.dart';
import 'package:go_router/go_router.dart';
import '../services/student_service.dart';

class ProfileTab extends StatefulWidget {
  const ProfileTab({super.key});

  @override
  State<ProfileTab> createState() => _ProfileTabState();
}

class _ProfileTabState extends State<ProfileTab> {
  bool _isLoading = true;
  Map<String, dynamic>? _profile;
  String _collegeName = 'No college';
  int _testCount = 0;
  int _avgPct = 0;
  int _badgesCount = 0;

  @override
  void initState() {
    super.initState();
    _fetchProfileData();
  }

  Future<void> _fetchProfileData() async {
    // Resolve integer student_id from the students table
    final profile = await StudentService.getProfile();
    final studentId = profile?['id'] as int? ?? 0;
    if (studentId == 0) {
      setState(() => _isLoading = false);
      return;
    }

    try {
      // Parallel fetches
      final futures = await Future.wait([
        // Profile already fetched via StudentService
        Future.value([profile!]),

        // Attempts
        Supabase.instance.client
            .from('attempts')
            .select('score,total_marks')
            .eq('student_id', studentId)
            .eq('status', 'completed'),

        // Badges
        Supabase.instance.client
            .from('student_badges')
            .select('badge_id')
            .eq('student_id', studentId),
      ]);

      final profiles = futures[0] as List<dynamic>;
      final attemptsData = futures[1] as List<dynamic>;
      final badges = futures[2] as List<dynamic>;

      if (profiles.isNotEmpty) {
        _profile = profiles[0] as Map<String, dynamic>;
        
        // Fetch college
        if (_profile?['college_id'] != null) {
          final college = await Supabase.instance.client
              .from('colleges')
              .select('name')
              .eq('id', _profile!['college_id'])
              .limit(1);
          if (college.isNotEmpty) {
            _collegeName = college[0]['name'] as String;
          }
        }
      }

      _testCount = attemptsData.length;
      if (_testCount > 0) {
        double totalPct = 0;
        for (var a in attemptsData) {
          if (a['total_marks'] > 0) {
            totalPct += (a['score'] * 100.0 / a['total_marks']);
          }
        }
        _avgPct = (totalPct / _testCount).round();
      }

      _badgesCount = badges.length;

      if (mounted) {
        setState(() => _isLoading = false);
      }
    } catch (e) {
      if (mounted) {
        setState(() => _isLoading = false);
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Error loading profile: $e'), backgroundColor: Colors.red),
        );
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_isLoading) {
      return const Center(child: CircularProgressIndicator());
    }

    if (_profile == null) {
      return const Center(child: Text('Profile not found'));
    }

    return RefreshIndicator(
      onRefresh: _fetchProfileData,
      child: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          _buildProfileHeader(),
          const SizedBox(height: 24),
          _buildStatsGrid(),
          const SizedBox(height: 24),
          _buildPrivateDetails(),
        ],
      ),
    );
  }

  Widget _buildProfileHeader() {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: const Color(0xFFE2E8F0)),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          if (_profile?['avatar_url'] != null)
            ClipRRect(
              borderRadius: BorderRadius.circular(36),
              // We'll use a placeholder since avatars might be SVGs which require flutter_svg
              child: Container(
                width: 72,
                height: 72,
                color: Colors.grey[200],
                child: const Icon(Icons.person, size: 40, color: Colors.grey),
              ),
            )
          else
            Container(
              width: 72,
              height: 72,
              decoration: BoxDecoration(color: Colors.grey[200], shape: BoxShape.circle),
              child: const Icon(Icons.person, size: 40, color: Colors.grey),
            ),
          const SizedBox(width: 16),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  _profile?['name'] ?? 'User',
                  style: const TextStyle(fontSize: 20, fontWeight: FontWeight.bold),
                ),
                Text(
                  _collegeName,
                  style: const TextStyle(fontSize: 13, color: Colors.grey),
                ),
                const SizedBox(height: 8),
                Wrap(
                  spacing: 8,
                  runSpacing: 8,
                  children: [
                    _badge(_profile?['level'] ?? 'Beginner', const Color(0xFFEFF6FF), const Color(0xFF4361EE)),
                    _badge('${_profile?['xp'] ?? 0} XP', Colors.white, const Color(0xFF4361EE), true),
                    _badge('${_profile?['streak'] ?? 0} day streak', Colors.white, Colors.black87),
                  ],
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _badge(String text, Color bgColor, Color textColor, [bool isBordered = false]) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
      decoration: BoxDecoration(
        color: bgColor,
        borderRadius: BorderRadius.circular(4),
        border: isBordered ? Border.all(color: const Color(0xFFE2E8F0)) : null,
      ),
      child: Text(
        text,
        style: TextStyle(color: textColor, fontSize: 12, fontWeight: FontWeight.bold),
      ),
    );
  }

  Widget _buildStatsGrid() {
    return GridView.count(
      shrinkWrap: true,
      physics: const NeverScrollableScrollPhysics(),
      crossAxisCount: 2,
      crossAxisSpacing: 12,
      mainAxisSpacing: 12,
      childAspectRatio: 1.5,
      children: [
        _statCard(_testCount.toString(), 'Tests Taken', const Color(0xFFE0F2FE), const Color(0xFF0284C7)),
        _statCard('$_avgPct%', 'Avg Score', const Color(0xFFDCFCE7), const Color(0xFF16A34A)),
        _statCard((_profile?['xp'] ?? 0).toString(), 'Total XP', const Color(0xFFEFF6FF), const Color(0xFF4361EE)),
        _statCard(_badgesCount.toString(), 'Badges', const Color(0xFFF1F5F9), const Color(0xFF475569)),
      ],
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

  Widget _buildPrivateDetails() {
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
          const Text('Private Details', style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold)),
          const SizedBox(height: 12),
          if (_profile?['phone'] != null) _detailRow('Phone:', _profile!['phone']),
          if (_profile?['email'] != null) _detailRow('Email:', _profile!['email']),
          if (_profile?['department'] != null) _detailRow('Department:', _profile!['department']),
          if (_profile?['semester'] != null) _detailRow('Semester:', _profile!['semester'].toString()),
          const SizedBox(height: 24),
          SizedBox(
            width: double.infinity,
            child: ElevatedButton.icon(
              onPressed: () => context.push('/subscription'),
              icon: const Icon(Icons.workspace_premium),
              label: const Text('Upgrade Plan'),
              style: ElevatedButton.styleFrom(
                backgroundColor: const Color(0xFFF97316),
                foregroundColor: Colors.white,
                padding: const EdgeInsets.symmetric(vertical: 12),
              ),
            ),
          ),
          const SizedBox(height: 12),
          SizedBox(
            width: double.infinity,
            child: OutlinedButton.icon(
              onPressed: () => context.push('/friends'),
              icon: const Icon(Icons.group),
              label: const Text('Friends & Challenges'),
              style: OutlinedButton.styleFrom(
                padding: const EdgeInsets.symmetric(vertical: 12),
              ),
            ),
          )
        ],
      ),
    );
  }

  Widget _detailRow(String label, String value) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 8.0),
      child: Row(
        children: [
          Text(label, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13, color: Colors.black87)),
          const SizedBox(width: 8),
          Expanded(child: Text(value, style: const TextStyle(fontSize: 13, color: Colors.black54))),
        ],
      ),
    );
  }
}
