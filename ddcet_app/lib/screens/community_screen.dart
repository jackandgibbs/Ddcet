import 'package:flutter/material.dart';
import 'package:supabase_flutter/supabase_flutter.dart';
import 'package:go_router/go_router.dart';
import '../services/student_service.dart';
import 'package:intl/intl.dart';

class CommunityScreen extends StatefulWidget {
  const CommunityScreen({super.key});

  @override
  State<CommunityScreen> createState() => _CommunityScreenState();
}

class _CommunityScreenState extends State<CommunityScreen> {
  bool _isLoading = true;
  String _activeTab = 'all'; // all, mine
  List<Map<String, dynamic>> _posts = [];
  Map<int, Map<String, dynamic>> _authors = {};
  Map<int, int> _commentCounts = {};
  
  int? _myStudentId;
  final TextEditingController _postController = TextEditingController();
  String _selectedCategory = 'discussion';

  final Map<String, String> _categories = {
    'discussion': 'Discussion',
    'study_tip': 'Study Tip',
    'doubt': 'Quick Doubt',
    'motivation': 'Motivation',
    'resource': 'Resource',
  };

  @override
  void initState() {
    super.initState();
    _fetchData();
  }

  @override
  void dispose() {
    _postController.dispose();
    super.dispose();
  }

  Future<void> _fetchData() async {
    _myStudentId = await StudentService.getStudentId();
    if (_myStudentId == null) {
      if (mounted) setState(() => _isLoading = false);
      return;
    }

    try {
      var query = Supabase.instance.client
          .from('community_posts')
          .select('*')
          .eq('is_visible', true)
          .order('is_pinned', ascending: false)
          .order('created_at', ascending: false)
          .limit(50);

      if (_activeTab == 'mine') {
        query = Supabase.instance.client
            .from('community_posts')
            .select('*')
            .eq('is_visible', true)
            .eq('student_id', _myStudentId!)
            .order('is_pinned', ascending: false)
            .order('created_at', ascending: false)
            .limit(50);
      }

      final postsRows = await query;
      final postsList = List<Map<String, dynamic>>.from(postsRows);
      
      if (postsList.isNotEmpty) {
        final postIds = postsList.map((p) => p['id'] as int).toList();
        final studentIds = postsList.map((p) => p['student_id'] as int).toSet().toList();

        // Fetch comments count
        final commentRows = await Supabase.instance.client
            .from('community_comments')
            .select('post_id')
            .inFilter('post_id', postIds);
            
        Map<int, int> counts = {};
        for (var c in commentRows) {
          int pid = c['post_id'];
          counts[pid] = (counts[pid] ?? 0) + 1;
        }

        // Fetch authors
        final authorRows = await Supabase.instance.client
            .from('students')
            .select('id,name,avatar_url')
            .inFilter('id', studentIds);
            
        Map<int, Map<String, dynamic>> authors = {};
        for (var a in authorRows) {
          authors[a['id'] as int] = a;
        }

        if (mounted) {
          setState(() {
            _commentCounts = counts;
            _authors = authors;
          });
        }
      }

      if (mounted) {
        setState(() {
          _posts = postsList;
          _isLoading = false;
        });
      }
    } catch (e) {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  Future<void> _createPost() async {
    final text = _postController.text.trim();
    if (text.isEmpty) return;
    
    setState(() => _isLoading = true);
    
    try {
      await Supabase.instance.client.from('community_posts').insert({
        'student_id': _myStudentId,
        'author_type': 'student',
        'body': text,
        'category': _selectedCategory,
        'is_visible': true,
      });
      _postController.clear();
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Posted successfully!'), backgroundColor: Colors.green));
      }
      _activeTab = 'mine'; // Switch to my posts to see it
      _fetchData();
    } catch (e) {
      setState(() => _isLoading = false);
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('Error: $e'), backgroundColor: Colors.red));
      }
    }
  }

  Future<void> _deletePost(int postId) async {
    try {
      await Supabase.instance.client
          .from('community_posts')
          .delete()
          .eq('id', postId)
          .eq('student_id', _myStudentId!); // Security check
      _fetchData();
    } catch (e) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Failed to delete post'), backgroundColor: Colors.red));
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFF1F5F9),
      appBar: AppBar(
        title: const Text('Community', style: TextStyle(fontWeight: FontWeight.bold)),
        leading: IconButton(icon: const Icon(Icons.arrow_back), onPressed: () => context.go('/dashboard')),
        backgroundColor: Colors.white,
        elevation: 1,
        bottom: PreferredSize(
          preferredSize: const Size.fromHeight(48),
          child: Row(
            children: [
              Expanded(
                child: _buildTabBtn('All Posts', 'all'),
              ),
              Expanded(
                child: _buildTabBtn('My Posts', 'mine'),
              ),
            ],
          ),
        ),
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator())
          : RefreshIndicator(
              onRefresh: _fetchData,
              child: ListView(
                padding: const EdgeInsets.all(16),
                children: [
                  _buildCreatePostCard(),
                  const SizedBox(height: 16),
                  if (_posts.isEmpty)
                    Center(
                      child: Padding(
                        padding: const EdgeInsets.all(32.0),
                        child: Column(
                          children: [
                            Icon(Icons.forum, size: 64, color: Colors.grey[400]),
                            const SizedBox(height: 16),
                            const Text('No posts yet', style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
                            Text('Be the first to start a discussion.', style: TextStyle(color: Colors.grey[600], fontSize: 14)),
                          ],
                        ),
                      ),
                    )
                  else
                    ..._posts.map((p) => _buildPostCard(p)),
                ],
              ),
            ),
    );
  }

  Widget _buildTabBtn(String label, String key) {
    final active = _activeTab == key;
    return InkWell(
      onTap: () {
        setState(() {
          _activeTab = key;
          _isLoading = true;
        });
        _fetchData();
      },
      child: Container(
        padding: const EdgeInsets.symmetric(vertical: 12),
        decoration: BoxDecoration(
          color: Colors.white,
          border: Border(bottom: BorderSide(color: active ? const Color(0xFF4361EE) : Colors.transparent, width: 3)),
        ),
        child: Text(label, textAlign: TextAlign.center, style: TextStyle(fontWeight: active ? FontWeight.bold : FontWeight.normal, color: active ? const Color(0xFF4361EE) : Colors.grey[600])),
      ),
    );
  }

  Widget _buildCreatePostCard() {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(12),
        boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.05), blurRadius: 10, offset: const Offset(0, 4))],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          TextField(
            controller: _postController,
            maxLines: 3,
            decoration: InputDecoration(
              hintText: 'Share a doubt, tip, or resource...',
              hintStyle: TextStyle(color: Colors.grey[400]),
              border: OutlineInputBorder(borderRadius: BorderRadius.circular(8), borderSide: BorderSide(color: Colors.grey[300]!)),
              enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(8), borderSide: BorderSide(color: Colors.grey[300]!)),
              focusedBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(8), borderSide: const BorderSide(color: Color(0xFF4361EE))),
              filled: true, fillColor: const Color(0xFFF8F9FA),
            ),
          ),
          const SizedBox(height: 12),
          Row(
            children: [
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 12),
                decoration: BoxDecoration(border: Border.all(color: Colors.grey[300]!), borderRadius: BorderRadius.circular(8)),
                child: DropdownButtonHideUnderline(
                  child: DropdownButton<String>(
                    value: _selectedCategory,
                    items: _categories.entries.map((e) => DropdownMenuItem(value: e.key, child: Text(e.value, style: const TextStyle(fontSize: 13)))).toList(),
                    onChanged: (v) => setState(() => _selectedCategory = v!),
                  ),
                ),
              ),
              const Spacer(),
              ElevatedButton(
                onPressed: _createPost,
                style: ElevatedButton.styleFrom(backgroundColor: const Color(0xFF4361EE), foregroundColor: Colors.white, shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8))),
                child: const Text('Post', style: TextStyle(fontWeight: FontWeight.bold)),
              )
            ],
          )
        ],
      ),
    );
  }

  Widget _buildPostCard(Map<String, dynamic> post) {
    final author = _authors[post['student_id']];
    final authorName = author?['name'] ?? 'DDCET Team';
    final avatar = author?['avatar_url'];
    final bool isPinned = post['is_pinned'] == true;
    final int commentCount = _commentCounts[post['id']] ?? 0;
    final bool isMine = post['student_id'] == _myStudentId;
    
    final catColor = _categoryColor(post['category']);
    final catName = _categories[post['category']] ?? 'Post';
    
    final date = DateTime.parse(post['created_at']).toLocal();
    final timeStr = DateFormat('d MMM yyyy, h:mm a').format(date);

    return Container(
      margin: const EdgeInsets.only(bottom: 16),
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(12),
        border: isPinned ? Border.all(color: const Color(0xFFF59E0B), width: 1.5) : Border.all(color: const Color(0xFFE2E8F0)),
        boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.02), blurRadius: 4, offset: const Offset(0, 2))],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          if (isPinned)
            Padding(
              padding: const EdgeInsets.only(bottom: 12),
              child: Row(children: [const Icon(Icons.push_pin, size: 14, color: Color(0xFFF59E0B)), const SizedBox(width: 4), Text('PINNED', style: TextStyle(fontSize: 11, fontWeight: FontWeight.bold, color: const Color(0xFFF59E0B), letterSpacing: 1.1))]),
            ),
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              CircleAvatar(
                radius: 18,
                backgroundColor: catColor.withValues(alpha: 0.2),
                backgroundImage: avatar != null ? NetworkImage(avatar) : null,
                child: avatar == null ? Text(authorName.substring(0, 1).toUpperCase(), style: TextStyle(color: catColor, fontWeight: FontWeight.bold)) : null,
              ),
              const SizedBox(width: 10),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Text(authorName, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 14)),
                        if (post['author_type'] == 'admin')
                          Padding(padding: const EdgeInsets.only(left: 4), child: Icon(Icons.verified, size: 14, color: Colors.blue)),
                      ],
                    ),
                    Text(timeStr, style: TextStyle(color: Colors.grey[500], fontSize: 11)),
                  ],
                ),
              ),
              if (isMine)
                IconButton(
                  icon: const Icon(Icons.delete_outline, size: 18, color: Colors.red),
                  padding: EdgeInsets.zero, constraints: const BoxConstraints(),
                  onPressed: () => _deletePost(post['id']),
                )
            ],
          ),
          const SizedBox(height: 12),
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
            decoration: BoxDecoration(color: catColor.withValues(alpha: 0.1), borderRadius: BorderRadius.circular(4)),
            child: Text(catName, style: TextStyle(fontSize: 10, fontWeight: FontWeight.bold, color: catColor)),
          ),
          if (post['title'] != null && post['title'].toString().isNotEmpty)
            Padding(
              padding: const EdgeInsets.only(top: 8),
              child: Text(post['title'], style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 15)),
            ),
          const SizedBox(height: 8),
          Text(post['body'] ?? '', style: const TextStyle(fontSize: 14, height: 1.5)),
          const Divider(height: 24),
          Row(
            children: [
              const Icon(Icons.favorite_border, size: 18, color: Colors.grey),
              const SizedBox(width: 4),
              const Text('Like', style: TextStyle(color: Colors.grey, fontSize: 13)),
              const SizedBox(width: 16),
              const Icon(Icons.chat_bubble_outline, size: 18, color: Colors.grey),
              const SizedBox(width: 4),
              Text('$commentCount Comments', style: const TextStyle(color: Colors.grey, fontSize: 13)),
              const Spacer(),
              TextButton(
                onPressed: () {
                  ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Comment viewer opening in next update')));
                },
                child: const Text('Reply', style: TextStyle(fontSize: 13)),
              )
            ],
          )
        ],
      ),
    );
  }

  Color _categoryColor(String? cat) {
    switch (cat) {
      case 'discussion': return const Color(0xFF4361EE);
      case 'study_tip': return const Color(0xFF10B981);
      case 'doubt': return const Color(0xFFEF4444);
      case 'motivation': return const Color(0xFFF59E0B);
      case 'resource': return const Color(0xFF8B5CF6);
      default: return Colors.grey;
    }
  }
}
