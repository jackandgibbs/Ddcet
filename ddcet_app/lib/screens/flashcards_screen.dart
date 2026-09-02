import 'package:flutter/material.dart';
import 'package:supabase_flutter/supabase_flutter.dart';
import 'package:go_router/go_router.dart';

class FlashcardsScreen extends StatefulWidget {
  const FlashcardsScreen({super.key});

  @override
  State<FlashcardsScreen> createState() => _FlashcardsScreenState();
}

class _FlashcardsScreenState extends State<FlashcardsScreen> {
  bool _isLoading = true;
  List<Map<String, dynamic>> _cards = [];
  List<String> _subjects = [];
  String _selectedSubject = '';
  final Set<int> _flippedCards = {};

  @override
  void initState() {
    super.initState();
    _fetchCards();
  }

  Future<void> _fetchCards() async {
    try {
      var query = Supabase.instance.client
          .from('flashcards')
          .select('*')
          .order('subject')
          .order('chapter');

      if (_selectedSubject.isNotEmpty) {
        query = Supabase.instance.client
            .from('flashcards')
            .select('*')
            .eq('subject', _selectedSubject)
            .order('subject')
            .order('chapter');
      }

      final rows = await query;
      _cards = List<Map<String, dynamic>>.from(rows);

      // Get unique subjects
      final subRows = await Supabase.instance.client
          .from('flashcards')
          .select('subject')
          .order('subject');
      _subjects = subRows.map((r) => r['subject'] as String).toSet().toList();

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
        title: const Text('Flashcards', style: TextStyle(fontWeight: FontWeight.bold)),
        leading: IconButton(icon: const Icon(Icons.arrow_back), onPressed: () => context.go('/dashboard')),
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator())
          : Column(
              children: [
                // Subject filter
                SizedBox(
                  height: 44,
                  child: ListView(
                    scrollDirection: Axis.horizontal,
                    padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 4),
                    children: [
                      _filterChip('All', _selectedSubject.isEmpty),
                      ..._subjects.map((s) => _filterChip(s, _selectedSubject == s)),
                    ],
                  ),
                ),
                const SizedBox(height: 8),
                Expanded(
                  child: _cards.isEmpty
                      ? const Center(child: Text('No flashcards available yet.', style: TextStyle(color: Colors.grey)))
                      : GridView.builder(
                          padding: const EdgeInsets.all(16),
                          gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                            crossAxisCount: 2, crossAxisSpacing: 12, mainAxisSpacing: 12, childAspectRatio: 0.85,
                          ),
                          itemCount: _cards.length,
                          itemBuilder: (context, index) => _buildFlashcard(index),
                        ),
                ),
              ],
            ),
    );
  }

  Widget _filterChip(String label, bool selected) {
    return Padding(
      padding: const EdgeInsets.only(right: 8),
      child: ChoiceChip(
        label: Text(label, style: TextStyle(fontSize: 12, fontWeight: FontWeight.bold,
            color: selected ? Colors.white : Colors.grey[700])),
        selected: selected,
        selectedColor: const Color(0xFF4361EE),
        backgroundColor: Colors.white,
        onSelected: (_) {
          setState(() {
            _selectedSubject = label == 'All' ? '' : label;
            _isLoading = true;
            _flippedCards.clear();
          });
          _fetchCards();
        },
      ),
    );
  }

  Widget _buildFlashcard(int index) {
    final card = _cards[index];
    final isFlipped = _flippedCards.contains(index);

    return GestureDetector(
      onTap: () => setState(() {
        if (isFlipped) {
          _flippedCards.remove(index);
        } else {
          _flippedCards.add(index);
        }
      }),
      child: AnimatedSwitcher(
        duration: const Duration(milliseconds: 400),
        child: Container(
          key: ValueKey('$index-$isFlipped'),
          padding: const EdgeInsets.all(16),
          decoration: BoxDecoration(
            color: isFlipped ? const Color(0xFFFEF3C7) : Colors.white,
            borderRadius: BorderRadius.circular(12),
            border: Border.all(color: isFlipped ? const Color(0xFFF59E0B) : const Color(0xFFE2E8F0)),
          ),
          child: Column(
            children: [
              // Subject tag
              Align(
                alignment: Alignment.topLeft,
                child: Text(card['subject'] ?? '', style: TextStyle(fontSize: 10, color: Colors.grey[500])),
              ),
              Expanded(
                child: Center(
                  child: Text(
                    isFlipped ? (card['back'] ?? card['answer'] ?? '') : (card['front'] ?? card['question'] ?? ''),
                    style: TextStyle(
                      fontSize: 13,
                      fontWeight: isFlipped ? FontWeight.bold : FontWeight.normal,
                      color: isFlipped ? const Color(0xFF92400E) : Colors.black87,
                      height: 1.4,
                    ),
                    textAlign: TextAlign.center,
                  ),
                ),
              ),
              Text(isFlipped ? 'Tap to flip back' : 'Tap to reveal', style: TextStyle(fontSize: 10, color: Colors.grey[400])),
            ],
          ),
        ),
      ),
    );
  }
}
