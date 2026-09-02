import 'package:flutter/material.dart';
import 'package:supabase_flutter/supabase_flutter.dart';
import 'package:go_router/go_router.dart';
import '../services/student_service.dart';

class AchievementsScreen extends StatefulWidget {
  const AchievementsScreen({super.key});

  @override
  State<AchievementsScreen> createState() => _AchievementsScreenState();
}

class _AchievementsScreenState extends State<AchievementsScreen> {
  bool _isLoading = true;
  int _currentXp = 0;
  String _currentLevel = 'Beginner';
  String? _nextLevel;
  int _xpToNext = 0;
  List<Map<String, dynamic>> _earnedBadges = [];
  List<Map<String, dynamic>> _allBadges = [];

  final _levels = {
    'Beginner': 0,
    'Bronze': 200,
    'Silver': 500,
    'Gold': 1500,
    'Diamond': 5000,
  };

  final _levelColors = {
    'Beginner': Colors.grey,
    'Bronze': const Color(0xFFCD7F32),
    'Silver': const Color(0xFFC0C0C0),
    'Gold': const Color(0xFFFFD700),
    'Diamond': const Color(0xFFB9F2FF),
  };

  @override
  void initState() {
    super.initState();
    _fetchData();
  }

  Future<void> _fetchData() async {
    final profile = await StudentService.getProfile();
    if (profile == null) {
      if (mounted) setState(() => _isLoading = false);
      return;
    }

    final studentId = profile['id'] as int;
    _currentXp = (profile['xp'] ?? 0) as int;

    // Determine level
    for (var entry in _levels.entries) {
      if (_currentXp >= entry.value) _currentLevel = entry.key;
    }

    // Find next level
    bool found = false;
    for (var entry in _levels.entries) {
      if (found) {
        _nextLevel = entry.key;
        _xpToNext = entry.value - _currentXp;
        break;
      }
      if (entry.key == _currentLevel) found = true;
    }

    try {
      final futures = await Future.wait([
        Supabase.instance.client
            .from('student_badges')
            .select('earned_at,badge_id,badges(name,icon,description)')
            .eq('student_id', studentId)
            .order('earned_at', ascending: false),
        Supabase.instance.client
            .from('badges')
            .select('*')
            .order('id'),
      ]);

      _earnedBadges = List<Map<String, dynamic>>.from(futures[0] as List);
      _allBadges = List<Map<String, dynamic>>.from(futures[1] as List);

      if (mounted) setState(() => _isLoading = false);
    } catch (e) {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFF8F9FA),
      appBar: AppBar(
        title: const Text('Achievements', style: TextStyle(fontWeight: FontWeight.bold)),
        leading: IconButton(icon: const Icon(Icons.arrow_back), onPressed: () => context.go('/dashboard')),
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator())
          : SingleChildScrollView(
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  // Level progress card
                  Container(
                    padding: const EdgeInsets.all(20),
                    decoration: BoxDecoration(
                      gradient: LinearGradient(
                        colors: [
                          _levelColors[_currentLevel] ?? Colors.grey,
                          (_levelColors[_currentLevel] ?? Colors.grey).withValues(alpha: 0.5),
                        ],
                      ),
                      borderRadius: BorderRadius.circular(16),
                    ),
                    child: Column(
                      children: [
                        Text(_currentLevel, style: const TextStyle(fontSize: 28, fontWeight: FontWeight.w900, color: Colors.white)),
                        const SizedBox(height: 4),
                        Text('$_currentXp XP', style: const TextStyle(fontSize: 16, color: Colors.white70, fontWeight: FontWeight.bold)),
                        if (_nextLevel != null) ...[
                          const SizedBox(height: 16),
                          ClipRRect(
                            borderRadius: BorderRadius.circular(8),
                            child: LinearProgressIndicator(
                              value: _xpToNext > 0
                                  ? 1 - (_xpToNext / (_levels[_nextLevel]! - _levels[_currentLevel]!))
                                  : 1,
                              minHeight: 8,
                              backgroundColor: Colors.white24,
                              valueColor: const AlwaysStoppedAnimation(Colors.white),
                            ),
                          ),
                          const SizedBox(height: 8),
                          Text('$_xpToNext XP to $_nextLevel', style: const TextStyle(color: Colors.white70, fontSize: 13)),
                        ],
                      ],
                    ),
                  ),

                  const SizedBox(height: 24),

                  // Level tiers
                  const Text('Level Tiers', style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
                  const SizedBox(height: 12),
                  Wrap(
                    spacing: 8,
                    runSpacing: 8,
                    children: _levels.entries.map((e) {
                      final unlocked = _currentXp >= e.value;
                      return Container(
                        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                        decoration: BoxDecoration(
                          color: unlocked ? (_levelColors[e.key] ?? Colors.grey).withValues(alpha: 0.15) : Colors.grey[100],
                          borderRadius: BorderRadius.circular(8),
                          border: Border.all(color: unlocked ? (_levelColors[e.key] ?? Colors.grey) : Colors.grey[300]!),
                        ),
                        child: Row(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            Icon(unlocked ? Icons.check_circle : Icons.lock, size: 16,
                                color: unlocked ? (_levelColors[e.key] ?? Colors.grey) : Colors.grey),
                            const SizedBox(width: 6),
                            Text('${e.key} (${e.value} XP)',
                                style: TextStyle(fontSize: 12, fontWeight: FontWeight.bold,
                                    color: unlocked ? Colors.black87 : Colors.grey)),
                          ],
                        ),
                      );
                    }).toList(),
                  ),

                  const SizedBox(height: 24),

                  // Badges
                  Text('Badges (${_earnedBadges.length}/${_allBadges.length})',
                      style: const TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
                  const SizedBox(height: 12),

                  if (_allBadges.isEmpty)
                    Container(
                      padding: const EdgeInsets.all(24),
                      decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(12)),
                      child: const Center(child: Text('No badges available yet.', style: TextStyle(color: Colors.grey))),
                    )
                  else
                    GridView.builder(
                      shrinkWrap: true,
                      physics: const NeverScrollableScrollPhysics(),
                      gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                        crossAxisCount: 3, crossAxisSpacing: 12, mainAxisSpacing: 12, childAspectRatio: 0.8,
                      ),
                      itemCount: _allBadges.length,
                      itemBuilder: (context, index) {
                        final badge = _allBadges[index];
                        final earned = _earnedBadges.any((eb) => eb['badge_id'] == badge['id']);
                        return Container(
                          padding: const EdgeInsets.all(12),
                          decoration: BoxDecoration(
                            color: earned ? Colors.white : Colors.grey[100],
                            borderRadius: BorderRadius.circular(12),
                            border: Border.all(color: earned ? const Color(0xFF4361EE) : Colors.grey[300]!),
                          ),
                          child: Column(
                            mainAxisAlignment: MainAxisAlignment.center,
                            children: [
                              Text(badge['icon'] ?? '🏅', style: TextStyle(fontSize: 28, color: earned ? null : Colors.grey)),
                              const SizedBox(height: 6),
                              Text(badge['name'] ?? '', textAlign: TextAlign.center,
                                  style: TextStyle(fontSize: 11, fontWeight: FontWeight.bold,
                                      color: earned ? Colors.black87 : Colors.grey)),
                              if (!earned)
                                const Icon(Icons.lock, size: 14, color: Colors.grey),
                            ],
                          ),
                        );
                      },
                    ),
                ],
              ),
            ),
    );
  }
}
