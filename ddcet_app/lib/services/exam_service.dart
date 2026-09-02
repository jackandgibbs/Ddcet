import 'dart:convert';
import 'package:http/http.dart' as http;
import 'student_service.dart';

class ExamService {
  static const String _baseUrl = 'https://ddcetprep.onrender.com';

  /// Generates a test (or resumes an in-progress one) by calling the PHP backend API.
  /// This ensures 100% parity with the web's smart adaptive logic.
  static Future<Map<String, dynamic>> generateExam(int? testId, String mode) async {
    final studentId = await StudentService.getStudentId();
    if (studentId == null) throw Exception("User not logged in");

    try {
      final response = await http.post(
        Uri.parse('$_baseUrl/api/generate_exam.php'),
        headers: {
          'Content-Type': 'application/json',
          'X-Student-Id': studentId.toString(),
        },
        body: jsonEncode({
          'test_id': testId,
          'mode': mode,
        }),
      );

      final data = jsonDecode(response.body);
      
      if (data['error'] != null) {
        throw Exception(data['error']);
      }
      
      // If we only got the attempt_id back, it means we generated a new test and need to fetch it
      if (data['ok'] == true && data['attempt_id'] != null && data['questions'] == null) {
        return resumeExam(data['attempt_id']);
      }
      
      return data;
    } catch (e) {
      throw Exception('Failed to generate exam: $e');
    }
  }

  /// Resumes an existing attempt and fetches the questions seeded in the correct order.
  static Future<Map<String, dynamic>> resumeExam(int attemptId) async {
    final studentId = await StudentService.getStudentId();
    if (studentId == null) throw Exception("User not logged in");

    try {
      final response = await http.post(
        Uri.parse('$_baseUrl/api/generate_exam.php'),
        headers: {
          'Content-Type': 'application/json',
          'X-Student-Id': studentId.toString(),
        },
        body: jsonEncode({
          'attempt_id': attemptId,
        }),
      );

      final data = jsonDecode(response.body);
      
      if (data['error'] != null) {
        throw Exception(data['error']);
      }
      
      return data;
    } catch (e) {
      throw Exception('Failed to resume exam: $e');
    }
  }

  /// Saves an individual answer by calling save_answer.php
  static Future<void> saveAnswer({
    required int attemptId,
    required int questionId,
    int? optionId,
    bool isFlagged = false,
    int timeSpent = 0,
  }) async {
    final studentId = await StudentService.getStudentId();
    if (studentId == null) return;

    try {
      await http.post(
        Uri.parse('$_baseUrl/api/save_answer.php'),
        headers: {
          'Content-Type': 'application/json',
          'X-Student-Id': studentId.toString(),
        },
        body: jsonEncode({
          'attempt_id': attemptId,
          'question_id': questionId,
          'option_id': optionId,
          'is_flagged': isFlagged,
          'time_spent': timeSpent,
        }),
      );
    } catch (e) {
      // Background save failure
    }
  }

  /// Submits the exam by calling submit_exam.php
  static Future<Map<String, dynamic>> submitExam(int attemptId, int timeSpent) async {
    final studentId = await StudentService.getStudentId();
    if (studentId == null) throw Exception("User not logged in");

    try {
      final response = await http.post(
        Uri.parse('$_baseUrl/api/submit_exam.php'),
        headers: {
          'Content-Type': 'application/json',
          'X-Student-Id': studentId.toString(),
        },
        body: jsonEncode({
          'attempt_id': attemptId,
          'time_spent_seconds': timeSpent,
        }),
      );

      final data = jsonDecode(response.body);
      if (data['error'] != null) {
        throw Exception(data['error']);
      }
      
      return data;
    } catch (e) {
      throw Exception('Failed to submit exam: $e');
    }
  }
}
