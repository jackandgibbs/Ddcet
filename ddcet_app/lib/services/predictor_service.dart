import 'package:flutter/services.dart';

class PredictorService {
  static List<Map<String, dynamic>> _data = [];
  static List<String> branches = [];
  static List<String> institutes = [];
  static List<String> cities = [];

  static Future<void> loadCSVData() async {
    if (_data.isNotEmpty) return;

    final csvFiles = ['assets/ddcet_full_data_2024.csv', 'assets/ddcet_full_data_2025.csv'];
    final Set<String> seen = {};

    for (var file in csvFiles) {
      try {
        final rawData = await rootBundle.loadString(file);
        final year = file.contains('2024') ? '2024' : '2025';
        
        List<String> lines = rawData.split('\n');
        if (lines.isEmpty) continue;
        
        // Skip header
        for (var i = 1; i < lines.length; i++) {
          final line = lines[i].trim();
          if (line.isEmpty) continue;
          
          List<String> row = line.split(',');
          if (row.length < 10) continue;

          final key = '$year|${row.join('|')}';
          if (seen.contains(key)) continue;
          seen.add(key);

          final inst = row[1].toString();
          final parts = inst.split(',');
          final college = parts[0].trim();
          final city = parts.length > 1 ? parts[1].trim() : '';

          _data.add({
            'year': year,
            'institute': inst,
            'college': college,
            'city': city,
            'inst_type': row[2].toString(),
            'branch': row[3].toString(),
            'category': row[4].toString(),
            'quota': row[5].toString(),
            'first_marks': double.tryParse(row[6].toString()) ?? 0.0,
            'first_rank': int.tryParse(row[7].toString()) ?? 0,
            'last_marks': double.tryParse(row[8].toString()) ?? 0.0,
            'last_rank': int.tryParse(row[9].toString()) ?? 0,
          });
        }
      } catch (e) {
        print("Error loading CSV: $e");
      }
    }

    branches = _data.map((e) => e['branch'] as String).toSet().toList()..sort();
    institutes = _data.map((e) => e['college'] as String).toSet().toList()..sort();
    cities = _data.map((e) => e['city'] as String).where((c) => c.isNotEmpty).toSet().toList()..sort();
  }

  static List<Map<String, dynamic>> predictColleges({
    required double marks,
    required int rank,
    required String category,
    required String quota,
    String? preferredBranch,
    String? preferredCity,
    String? preferredInstitute,
  }) {
    List<Map<String, dynamic>> results = [];
    
    // Sort logic from PHP:
    // If marks are provided and it's 2024, it filters. If 2025, it uses rank.
    // The PHP code does complex logic, we will mimic basic cutoff matching.
    for (var row in _data) {
      if (row['category'] != category) continue;
      if (quota != 'ALL' && row['quota'] != quota) continue;

      if (preferredBranch != null && preferredBranch.isNotEmpty && row['branch'] != preferredBranch) continue;
      if (preferredCity != null && preferredCity.isNotEmpty && row['city'] != preferredCity) continue;
      if (preferredInstitute != null && preferredInstitute.isNotEmpty && row['college'] != preferredInstitute) continue;

      bool possible = false;
      double probability = 0.0;

      if (row['year'] == '2024') {
        if (marks > 0) {
          double lastMarks = row['last_marks'];
          double firstMarks = row['first_marks'];
          if (marks >= lastMarks) {
            possible = true;
            if (firstMarks > lastMarks) {
              probability = 50 + ((marks - lastMarks) / (firstMarks - lastMarks) * 50);
            } else {
              probability = 99.0;
            }
          } else if (marks >= lastMarks - 5) { // Borderline
            possible = true;
            probability = 10 + ((marks - (lastMarks - 5)) / 5 * 30);
          }
        }
      } else { // 2025
        if (rank > 0) {
          int lastRank = row['last_rank'];
          int firstRank = row['first_rank'];
          if (lastRank > 0 && rank <= lastRank) {
            possible = true;
            if (lastRank > firstRank) {
              probability = 50 + ((lastRank - rank) / (lastRank - firstRank) * 50);
            } else {
              probability = 99.0;
            }
          } else if (lastRank > 0 && rank <= lastRank + 500) { // Borderline
            possible = true;
            probability = 10 + (((lastRank + 500) - rank) / 500 * 30);
          }
        }
      }

      if (possible) {
        probability = probability.clamp(1.0, 99.0);
        results.add({
          'college': row['college'],
          'city': row['city'],
          'branch': row['branch'],
          'category': row['category'],
          'quota': row['quota'],
          'year': row['year'],
          'last_cutoff': row['year'] == '2024' ? row['last_marks'] : row['last_rank'],
          'probability': probability,
        });
      }
    }

    results.sort((a, b) => b['probability'].compareTo(a['probability']));
    return results;
  }
}
