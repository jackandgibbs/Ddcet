import 'package:flutter/material.dart';
import 'package:supabase_flutter/supabase_flutter.dart';
import 'package:go_router/go_router.dart';
import '../services/student_service.dart';

class FriendsScreen extends StatefulWidget {
  const FriendsScreen({super.key});

  @override
  State<FriendsScreen> createState() => _FriendsScreenState();
}

class _FriendsScreenState extends State<FriendsScreen> {
  final _emailController = TextEditingController();
  bool _isLoading = true;
  bool _isSending = false;
  
  List<Map<String, dynamic>> _myFriends = [];
  List<Map<String, dynamic>> _pendingRequests = [];
  List<Map<String, dynamic>> _sentRequests = [];
  
  int? _myStudentId;
  String? _myEmail;

  @override
  void initState() {
    super.initState();
    _loadFriendsData();
  }

  @override
  void dispose() {
    _emailController.dispose();
    super.dispose();
  }

  Future<void> _loadFriendsData() async {
    _myStudentId = await StudentService.getStudentId();
    final profile = await StudentService.getProfile();
    _myEmail = profile?['email'];

    if (_myStudentId == null) {
      if (mounted) setState(() => _isLoading = false);
      return;
    }

    try {
      // Fetch all friend records involving me
      final rows = await Supabase.instance.client
          .from('friends')
          .select('id, student_id, friend_id, status, students!friends_student_id_fkey(id,name,email,avatar_url,level,xp,daily_streak), friend:students!friends_friend_id_fkey(id,name,email,avatar_url,level,xp,daily_streak)')
          .or('student_id.eq.$_myStudentId,friend_id.eq.$_myStudentId');

      List<Map<String, dynamic>> myFriends = [];
      List<Map<String, dynamic>> pendingRequests = [];
      List<Map<String, dynamic>> sentRequests = [];

      for (var row in rows) {
        final status = row['status'];
        final isSender = row['student_id'] == _myStudentId;
        final otherPerson = isSender ? row['friend'] : row['students'];
        
        if (otherPerson == null) continue;
        
        final mapData = {
          'request_id': row['id'],
          ...otherPerson as Map<String, dynamic>,
        };

        if (status == 'accepted') {
          myFriends.add(mapData);
        } else if (status == 'pending') {
          if (isSender) {
            sentRequests.add(mapData);
          } else {
            pendingRequests.add(mapData);
          }
        }
      }

      if (mounted) {
        setState(() {
          _myFriends = myFriends;
          _pendingRequests = pendingRequests;
          _sentRequests = sentRequests;
          _isLoading = false;
        });
      }
    } catch (e) {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  Future<void> _addFriend() async {
    final email = _emailController.text.trim();
    if (email.isEmpty) return;

    if (email.toLowerCase() == _myEmail?.toLowerCase()) {
      _showError("You can't send a friend request to yourself.");
      return;
    }

    setState(() => _isSending = true);
    
    try {
      final targetRows = await Supabase.instance.client
          .from('students')
          .select('id')
          .eq('email', email)
          .neq('id', _myStudentId!)
          .limit(1);

      if (targetRows.isEmpty) {
        _showError('No user found with that email.');
        setState(() => _isSending = false);
        return;
      }

      final targetId = targetRows[0]['id'];

      // Check if request exists
      final existing = await Supabase.instance.client
          .from('friends')
          .select('status')
          .or('and(student_id.eq.$_myStudentId,friend_id.eq.$targetId),and(student_id.eq.$targetId,friend_id.eq.$_myStudentId)')
          .limit(1);

      if (existing.isNotEmpty) {
        final st = existing[0]['status'];
        if (st == 'accepted') _showError("You're already friends with this user.");
        else if (st == 'pending') _showError('There is already a pending request between you two.');
        else _showError('A previous request exists between you two.');
      } else {
        await Supabase.instance.client.from('friends').insert({
          'student_id': _myStudentId,
          'friend_id': targetId,
          'status': 'pending',
        });
        
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(content: Text('Friend request sent!'), backgroundColor: Colors.green),
          );
        }
        _emailController.clear();
        _loadFriendsData();
      }
    } catch (e) {
      _showError('Could not send request. Error: $e');
    }
    
    if (mounted) setState(() => _isSending = false);
  }

  Future<void> _updateRequest(int requestId, String newStatus) async {
    try {
      await Supabase.instance.client
          .from('friends')
          .update({'status': newStatus})
          .eq('id', requestId)
          .eq('friend_id', _myStudentId!); // Ensure only receiver can update
      _loadFriendsData();
    } catch (e) {
      _showError('Could not update request.');
    }
  }

  void _showError(String message) {
    if (mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(message), backgroundColor: Colors.red),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFF8F9FA),
      appBar: AppBar(
        title: const Text('Friends & Challenges', style: TextStyle(fontWeight: FontWeight.bold)),
        leading: IconButton(icon: const Icon(Icons.arrow_back), onPressed: () => context.go('/dashboard')),
      ),
      body: _isLoading 
        ? const Center(child: CircularProgressIndicator())
        : RefreshIndicator(
            onRefresh: _loadFriendsData,
            child: ListView(
              padding: const EdgeInsets.all(16),
              children: [
                _buildAddFriendCard(),
                const SizedBox(height: 16),
                if (_pendingRequests.isNotEmpty) ...[
                  _buildPendingRequestsCard(),
                  const SizedBox(height: 16),
                ],
                if (_sentRequests.isNotEmpty) ...[
                  _buildSentRequestsCard(),
                  const SizedBox(height: 16),
                ],
                _buildMyFriendsCard(),
              ],
            ),
          ),
    );
  }

  Widget _buildAddFriendCard() {
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
          const Text('Add Friend', style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
          const SizedBox(height: 12),
          Row(
            children: [
              Expanded(
                child: TextField(
                  controller: _emailController,
                  decoration: InputDecoration(
                    hintText: "Friend's email address",
                    border: OutlineInputBorder(borderRadius: BorderRadius.circular(8)),
                    contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 0),
                  ),
                ),
              ),
              const SizedBox(width: 12),
              ElevatedButton(
                onPressed: _isSending ? null : _addFriend,
                style: ElevatedButton.styleFrom(
                  backgroundColor: const Color(0xFF4361EE),
                  foregroundColor: Colors.white,
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                ),
                child: _isSending 
                    ? const SizedBox(width: 16, height: 16, child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2))
                    : const Text('Send'),
              )
            ],
          ),
        ],
      ),
    );
  }

  Widget _buildPendingRequestsCard() {
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
          Text('Friend Requests (${_pendingRequests.length})', style: const TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
          const SizedBox(height: 8),
          ..._pendingRequests.map((req) {
            return ListTile(
              contentPadding: EdgeInsets.zero,
              leading: _avatarFor(req),
              title: Text(req['name'] ?? 'Student', style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 14)),
              subtitle: Text(req['email'] ?? '', style: const TextStyle(fontSize: 12)),
              trailing: Row(
                mainAxisSize: MainAxisSize.min,
                children: [
                  TextButton(
                    onPressed: () => _updateRequest(req['request_id'], 'rejected'), 
                    child: const Text('Decline', style: TextStyle(color: Colors.grey, fontSize: 13))
                  ),
                  ElevatedButton(
                    onPressed: () => _updateRequest(req['request_id'], 'accepted'),
                    style: ElevatedButton.styleFrom(
                      backgroundColor: const Color(0xFF4361EE), foregroundColor: Colors.white,
                      padding: const EdgeInsets.symmetric(horizontal: 12),
                      minimumSize: const Size(0, 36),
                    ),
                    child: const Text('Accept', style: TextStyle(fontSize: 13)),
                  )
                ],
              ),
            );
          }),
        ],
      ),
    );
  }

  Widget _buildSentRequestsCard() {
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
          Text('Sent Requests (${_sentRequests.length})', style: const TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
          const SizedBox(height: 8),
          ..._sentRequests.map((req) {
            return ListTile(
              contentPadding: EdgeInsets.zero,
              leading: _avatarFor(req),
              title: Text(req['name'] ?? 'Student', style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 14)),
              subtitle: Text(req['email'] ?? '', style: const TextStyle(fontSize: 12)),
              trailing: const Chip(label: Text('Pending', style: TextStyle(fontSize: 11)), backgroundColor: Color(0xFFF1F5F9)),
            );
          }),
        ],
      ),
    );
  }

  Widget _buildMyFriendsCard() {
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
          Text('My Friends (${_myFriends.length})', style: const TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
          const SizedBox(height: 12),
          if (_myFriends.isEmpty)
            const Text('No friends yet. Add some to compare scores!', style: TextStyle(color: Colors.grey))
          else
            ..._myFriends.map((friend) {
              return Padding(
                padding: const EdgeInsets.only(bottom: 12),
                child: Row(
                  children: [
                    _avatarFor(friend),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(friend['name'] ?? 'Student', style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 14)),
                          Row(
                            children: [
                              Text('${friend['level'] ?? 'Beginner'} • ', style: const TextStyle(fontSize: 11, color: Colors.grey)),
                              Text('${friend['xp'] ?? 0} XP • ', style: const TextStyle(fontSize: 11, color: Color(0xFF4361EE))),
                              const Icon(Icons.local_fire_department, size: 12, color: Colors.orange),
                              Text('${friend['daily_streak'] ?? 0}', style: const TextStyle(fontSize: 11, color: Colors.grey)),
                            ],
                          )
                        ],
                      ),
                    ),
                    ElevatedButton.icon(
                      onPressed: () {
                        // Challenge functionality to be added later
                        ScaffoldMessenger.of(context).showSnackBar(
                          const SnackBar(content: Text('Friend challenges coming soon!')),
                        );
                      },
                      icon: const Icon(Icons.sports_esports, size: 16),
                      label: const Text('Challenge', style: TextStyle(fontSize: 12)),
                      style: ElevatedButton.styleFrom(
                        backgroundColor: const Color(0xFF1E293B),
                        foregroundColor: Colors.white,
                        padding: const EdgeInsets.symmetric(horizontal: 12),
                        minimumSize: const Size(0, 36),
                      ),
                    )
                  ],
                ),
              );
            }),
        ],
      ),
    );
  }

  Widget _avatarFor(Map<String, dynamic> user) {
    if (user['avatar_url'] != null) {
      return CircleAvatar(backgroundImage: NetworkImage(user['avatar_url']));
    }
    final name = user['name'] ?? 'S';
    return CircleAvatar(
      backgroundColor: const Color(0xFFEFF6FF),
      child: Text(name.substring(0, 1).toUpperCase(), style: const TextStyle(color: Color(0xFF4361EE), fontWeight: FontWeight.bold)),
    );
  }
}
