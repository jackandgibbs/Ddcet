import 'package:flutter/material.dart';
import 'dart:convert';
import 'package:http/http.dart' as http;
import 'package:flutter_dotenv/flutter_dotenv.dart';

class AiTutorTab extends StatefulWidget {
  const AiTutorTab({super.key});

  @override
  State<AiTutorTab> createState() => _AiTutorTabState();
}

class _AiTutorTabState extends State<AiTutorTab> {
  final TextEditingController _controller = TextEditingController();
  final ScrollController _scrollController = ScrollController();
  final List<Map<String, String>> _messages = [
    {
      'role': 'assistant',
      'content': 'Hi! I\'m your DDCET AI Tutor. Ask me to explain concepts, give practice questions, or analyze topics. How can I help you today?'
    }
  ];
  bool _isLoading = false;
  String _streamedText = '';

  final _systemPrompt = '''You are an expert DDCET (Diploma to Degree Common Entrance Test) tutor for Gujarat, India.
Your role is to help students prepare for the DDCET exam by:
- Explaining concepts in Science, Maths, Engineering, and Soft Skills
- Providing practice questions with step-by-step solutions
- Analyzing performance and suggesting improvements
- Answering doubts about syllabus topics
Keep answers concise, clear, and exam-focused. Use examples where helpful.
Respond in English. Use bullet points for clarity.''';

  @override
  void dispose() {
    _controller.dispose();
    _scrollController.dispose();
    super.dispose();
  }

  void _scrollToBottom() {
    Future.delayed(const Duration(milliseconds: 100), () {
      if (_scrollController.hasClients) {
        _scrollController.animateTo(
          _scrollController.position.maxScrollExtent,
          duration: const Duration(milliseconds: 300),
          curve: Curves.easeOut,
        );
      }
    });
  }

  Future<void> _sendMessage() async {
    if (_controller.text.trim().isEmpty || _isLoading) return;

    final userMessage = _controller.text.trim();
    setState(() {
      _messages.add({'role': 'user', 'content': userMessage});
      _isLoading = true;
      _streamedText = '';
    });
    _controller.clear();
    _scrollToBottom();

    try {
      final apiKey = dotenv.env['GEMINI_API_KEY'] ?? '';
      if (apiKey.isEmpty) {
        _addAssistantMessage('API key not configured. Please add GEMINI_API_KEY to your .env file.');
        return;
      }

      // Build conversation history for context
      final contents = <Map<String, dynamic>>[];
      
      // Add system instruction as the first user message context
      contents.add({
        'role': 'user',
        'parts': [{'text': _systemPrompt}],
      });
      contents.add({
        'role': 'model',
        'parts': [{'text': 'Understood! I\'m ready to help with DDCET preparation.'}],
      });

      // Add conversation history (last 10 messages for context window)
      final historyMessages = _messages.length > 12 ? _messages.sublist(_messages.length - 12) : _messages;
      for (var msg in historyMessages) {
        contents.add({
          'role': msg['role'] == 'user' ? 'user' : 'model',
          'parts': [{'text': msg['content']!}],
        });
      }

      // Use streaming endpoint
      final url = Uri.parse(
          'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:streamGenerateContent?alt=sse&key=$apiKey');

      final request = http.Request('POST', url);
      request.headers['Content-Type'] = 'application/json';
      request.body = jsonEncode({
        'contents': contents,
        'generationConfig': {
          'temperature': 0.7,
          'maxOutputTokens': 1024,
        },
      });

      final response = await http.Client().send(request);

      if (response.statusCode == 200) {
        final stream = response.stream.transform(utf8.decoder);
        StringBuffer buffer = StringBuffer();

        await for (var chunk in stream) {
          // SSE format: "data: {...}\n\n"
          final lines = chunk.split('\n');
          for (var line in lines) {
            if (line.startsWith('data: ')) {
              final jsonStr = line.substring(6).trim();
              if (jsonStr.isEmpty || jsonStr == '[DONE]') continue;
              try {
                final data = jsonDecode(jsonStr);
                final text = data['candidates']?[0]?['content']?['parts']?[0]?['text'] ?? '';
                if (text.isNotEmpty) {
                  buffer.write(text);
                  if (mounted) {
                    setState(() => _streamedText = buffer.toString());
                    _scrollToBottom();
                  }
                }
              } catch (_) {
                // Skip malformed JSON chunks
              }
            }
          }
        }

        // Finalize the streamed message
        if (buffer.isNotEmpty) {
          if (mounted) {
            setState(() {
              _messages.add({'role': 'assistant', 'content': buffer.toString()});
              _streamedText = '';
              _isLoading = false;
            });
            _scrollToBottom();
          }
        } else {
          _addAssistantMessage('No response received. Please try again.');
        }
      } else {
        final body = await response.stream.bytesToString();
        _addAssistantMessage('Error (${response.statusCode}): Could not reach AI. Please try again.');
      }
    } catch (e) {
      _addAssistantMessage('Connection error. Check your internet and try again.');
    }
  }

  void _addAssistantMessage(String text) {
    if (mounted) {
      setState(() {
        _messages.add({'role': 'assistant', 'content': text});
        _streamedText = '';
        _isLoading = false;
      });
      _scrollToBottom();
    }
  }

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        // Header
        Container(
          padding: const EdgeInsets.all(16),
          decoration: const BoxDecoration(
            gradient: LinearGradient(colors: [Color(0xFF8B5CF6), Color(0xFF6D28D9)]),
          ),
          width: double.infinity,
          child: SafeArea(
            bottom: false,
            child: Row(
              children: [
                Container(
                  padding: const EdgeInsets.all(8),
                  decoration: BoxDecoration(
                    color: Colors.white.withValues(alpha: 0.2),
                    borderRadius: BorderRadius.circular(10),
                  ),
                  child: const Icon(Icons.psychology, color: Colors.white, size: 28),
                ),
                const SizedBox(width: 12),
                const Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text('AI Tutor', style: TextStyle(color: Colors.white, fontSize: 18, fontWeight: FontWeight.bold)),
                    Text('Powered by Gemini', style: TextStyle(color: Colors.white70, fontSize: 11)),
                  ],
                ),
                const Spacer(),
                IconButton(
                  icon: const Icon(Icons.delete_outline, color: Colors.white70),
                  onPressed: () {
                    setState(() {
                      _messages.clear();
                      _messages.add({
                        'role': 'assistant',
                        'content': 'Chat cleared! How can I help you with DDCET preparation?'
                      });
                    });
                  },
                  tooltip: 'Clear chat',
                ),
              ],
            ),
          ),
        ),

        // Quick action chips
        Container(
          padding: const EdgeInsets.symmetric(vertical: 8),
          color: const Color(0xFFF8F9FA),
          child: SizedBox(
            height: 36,
            child: ListView(
              scrollDirection: Axis.horizontal,
              padding: const EdgeInsets.symmetric(horizontal: 12),
              children: [
                _quickChip('📝 Practice Questions'),
                _quickChip('📖 Explain a Topic'),
                _quickChip('🧮 Solve Maths'),
                _quickChip('⚡ Quick Tips'),
              ],
            ),
          ),
        ),

        // Messages
        Expanded(
          child: ListView.builder(
            controller: _scrollController,
            padding: const EdgeInsets.all(16),
            itemCount: _messages.length + (_isLoading && _streamedText.isNotEmpty ? 1 : 0) + (_isLoading && _streamedText.isEmpty ? 1 : 0),
            itemBuilder: (context, index) {
              // Typing indicator (when waiting and no stream yet)
              if (_isLoading && _streamedText.isEmpty && index == _messages.length) {
                return _buildTypingIndicator();
              }
              // Streaming message
              if (_isLoading && _streamedText.isNotEmpty && index == _messages.length) {
                return _buildMessageBubble('assistant', _streamedText, isStreaming: true);
              }

              final msg = _messages[index];
              return _buildMessageBubble(msg['role']!, msg['content']!);
            },
          ),
        ),

        // Input
        Container(
          padding: const EdgeInsets.all(12),
          decoration: BoxDecoration(
            color: Colors.white,
            boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.05), blurRadius: 10, offset: const Offset(0, -2))],
          ),
          child: SafeArea(
            child: Row(
              children: [
                Expanded(
                  child: TextField(
                    controller: _controller,
                    decoration: InputDecoration(
                      hintText: 'Ask about DDCET topics...',
                      hintStyle: TextStyle(color: Colors.grey[400]),
                      border: OutlineInputBorder(borderRadius: BorderRadius.circular(24), borderSide: BorderSide.none),
                      filled: true,
                      fillColor: const Color(0xFFF1F5F9),
                      contentPadding: const EdgeInsets.symmetric(horizontal: 20, vertical: 12),
                    ),
                    onSubmitted: (_) => _sendMessage(),
                    enabled: !_isLoading,
                  ),
                ),
                const SizedBox(width: 8),
                CircleAvatar(
                  backgroundColor: _isLoading ? Colors.grey : const Color(0xFF8B5CF6),
                  child: IconButton(
                    icon: const Icon(Icons.send, color: Colors.white, size: 18),
                    onPressed: _isLoading ? null : _sendMessage,
                  ),
                ),
              ],
            ),
          ),
        ),
      ],
    );
  }

  Widget _quickChip(String label) {
    return Padding(
      padding: const EdgeInsets.only(right: 8),
      child: ActionChip(
        label: Text(label, style: const TextStyle(fontSize: 12)),
        backgroundColor: Colors.white,
        side: const BorderSide(color: Color(0xFFE2E8F0)),
        onPressed: () {
          _controller.text = label.replaceAll(RegExp(r'^.{2} '), '');
          _sendMessage();
        },
      ),
    );
  }

  Widget _buildMessageBubble(String role, String content, {bool isStreaming = false}) {
    final isUser = role == 'user';
    return Align(
      alignment: isUser ? Alignment.centerRight : Alignment.centerLeft,
      child: Container(
        margin: const EdgeInsets.only(bottom: 12),
        padding: const EdgeInsets.all(14),
        constraints: BoxConstraints(maxWidth: MediaQuery.of(context).size.width * 0.82),
        decoration: BoxDecoration(
          color: isUser ? const Color(0xFF8B5CF6) : Colors.white,
          border: isUser ? null : Border.all(color: const Color(0xFFE2E8F0)),
          borderRadius: BorderRadius.only(
            topLeft: const Radius.circular(16),
            topRight: const Radius.circular(16),
            bottomLeft: isUser ? const Radius.circular(16) : Radius.zero,
            bottomRight: isUser ? Radius.zero : const Radius.circular(16),
          ),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              content,
              style: TextStyle(color: isUser ? Colors.white : Colors.black87, fontSize: 14, height: 1.5),
            ),
            if (isStreaming) ...[
              const SizedBox(height: 4),
              SizedBox(width: 12, height: 12, child: CircularProgressIndicator(strokeWidth: 1.5, color: Colors.grey[400])),
            ],
          ],
        ),
      ),
    );
  }

  Widget _buildTypingIndicator() {
    return Align(
      alignment: Alignment.centerLeft,
      child: Container(
        margin: const EdgeInsets.only(bottom: 12),
        padding: const EdgeInsets.all(14),
        decoration: BoxDecoration(
          color: Colors.white,
          border: Border.all(color: const Color(0xFFE2E8F0)),
          borderRadius: const BorderRadius.only(
            topLeft: Radius.circular(16),
            topRight: Radius.circular(16),
            bottomRight: Radius.circular(16),
          ),
        ),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            SizedBox(width: 16, height: 16, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.grey[400])),
            const SizedBox(width: 8),
            Text('Thinking...', style: TextStyle(color: Colors.grey[500], fontSize: 13)),
          ],
        ),
      ),
    );
  }
}
