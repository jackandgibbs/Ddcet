import 'package:supabase_flutter/supabase_flutter.dart';

/// Resolves the Supabase Auth UUID → integer student ID from the `students` table.
/// The website stores students with integer PKs; `google_id` holds the auth UUID.
class StudentService {
  static int? _cachedStudentId;
  static Map<String, dynamic>? _cachedProfile;

  /// Returns the integer student_id from the `students` table.
  /// Looks up by `google_id` (the auth UUID). Returns null if not found.
  static Future<int?> getStudentId() async {
    if (_cachedStudentId != null) return _cachedStudentId;

    final authUser = Supabase.instance.client.auth.currentUser;
    if (authUser == null) return null;

    try {
      final rows = await Supabase.instance.client
          .from('students')
          .select('id')
          .eq('google_id', authUser.id)
          .limit(1);

      if (rows.isNotEmpty) {
        _cachedStudentId = rows[0]['id'] as int;
        return _cachedStudentId;
      }
    } catch (e) {
      // ignore
    }
    return null;
  }

  /// Returns full student profile row, cached.
  static Future<Map<String, dynamic>?> getProfile() async {
    if (_cachedProfile != null) return _cachedProfile;

    final authUser = Supabase.instance.client.auth.currentUser;
    if (authUser == null) return null;

    try {
      final rows = await Supabase.instance.client
          .from('students')
          .select('*')
          .eq('google_id', authUser.id)
          .limit(1);

      if (rows.isNotEmpty) {
        _cachedProfile = rows[0] as Map<String, dynamic>;
        _cachedStudentId = _cachedProfile!['id'] as int?;
        return _cachedProfile;
      }
    } catch (e) {
      // ignore
    }
    return null;
  }

  /// Call on logout to clear the cache.
  static void clearCache() {
    _cachedStudentId = null;
    _cachedProfile = null;
  }
}
