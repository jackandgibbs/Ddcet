import 'package:flutter/material.dart';
import '../services/predictor_service.dart';

class PredictorScreen extends StatefulWidget {
  const PredictorScreen({super.key});

  @override
  State<PredictorScreen> createState() => _PredictorScreenState();
}

class _PredictorScreenState extends State<PredictorScreen> {
  final _marksController = TextEditingController();
  final _rankController = TextEditingController();
  
  String _category = 'OPEN';
  String _quota = 'ALL';
  String? _selectedBranch;
  String? _selectedCity;
  
  List<Map<String, dynamic>> _results = [];
  bool _hasSearched = false;

  @override
  void initState() {
    super.initState();
    PredictorService.loadCSVData().then((_) {
      setState(() {}); // refresh after data loads
    });
  }

  void _predict() {
    double marks = double.tryParse(_marksController.text) ?? 0.0;
    int rank = int.tryParse(_rankController.text) ?? 0;

    if (marks <= 0 && rank <= 0) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Please enter valid marks or rank')));
      return;
    }

    final results = PredictorService.predictColleges(
      marks: marks,
      rank: rank,
      category: _category,
      quota: _quota,
      preferredBranch: _selectedBranch == 'All' ? null : _selectedBranch,
      preferredCity: _selectedCity == 'All' ? null : _selectedCity,
    );

    setState(() {
      _results = results;
      _hasSearched = true;
    });
  }

  @override
  Widget build(BuildContext context) {
    if (PredictorService.branches.isEmpty) {
      return const Scaffold(body: Center(child: CircularProgressIndicator()));
    }

    return Scaffold(
      appBar: AppBar(
        title: const Text('College Predictor', style: TextStyle(fontWeight: FontWeight.bold)),
        backgroundColor: Colors.white,
      ),
      body: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // Sidebar Filters
          Container(
            width: 300,
            color: Colors.white,
            padding: const EdgeInsets.all(16),
            child: SingleChildScrollView(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Text('Enter Your Details', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 18)),
                  const SizedBox(height: 16),
                  TextField(
                    controller: _marksController,
                    decoration: const InputDecoration(labelText: 'Expected Marks (out of 200)', border: OutlineInputBorder()),
                    keyboardType: TextInputType.number,
                  ),
                  const SizedBox(height: 16),
                  TextField(
                    controller: _rankController,
                    decoration: const InputDecoration(labelText: 'Expected Rank (Optional)', border: OutlineInputBorder()),
                    keyboardType: TextInputType.number,
                  ),
                  const SizedBox(height: 16),
                  DropdownButtonFormField<String>(
                    value: _category,
                    decoration: const InputDecoration(labelText: 'Category', border: OutlineInputBorder()),
                    items: ['OPEN', 'OBC', 'SC', 'ST', 'EWS'].map((c) => DropdownMenuItem(value: c, child: Text(c))).toList(),
                    onChanged: (v) => setState(() => _category = v!),
                  ),
                  const SizedBox(height: 16),
                  DropdownButtonFormField<String>(
                    value: _selectedBranch,
                    decoration: const InputDecoration(labelText: 'Preferred Branch', border: OutlineInputBorder()),
                    items: ['All', ...PredictorService.branches].map((c) => DropdownMenuItem(value: c, child: Text(c))).toList(),
                    onChanged: (v) => setState(() => _selectedBranch = v),
                  ),
                  const SizedBox(height: 16),
                  DropdownButtonFormField<String>(
                    value: _selectedCity,
                    decoration: const InputDecoration(labelText: 'Preferred City', border: OutlineInputBorder()),
                    items: ['All', ...PredictorService.cities].map((c) => DropdownMenuItem(value: c, child: Text(c))).toList(),
                    onChanged: (v) => setState(() => _selectedCity = v),
                  ),
                  const SizedBox(height: 24),
                  SizedBox(
                    width: double.infinity,
                    height: 50,
                    child: ElevatedButton(
                      style: ElevatedButton.styleFrom(backgroundColor: const Color(0xFF4361EE), foregroundColor: Colors.white),
                      onPressed: _predict,
                      child: const Text('Predict Colleges', style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold)),
                    ),
                  )
                ],
              ),
            ),
          ),
          
          // Results Area
          Expanded(
            child: Container(
              color: const Color(0xFFF8F9FA),
              padding: const EdgeInsets.all(16),
              child: !_hasSearched
                  ? const Center(child: Text('Enter your details and click Predict to see matched colleges.', style: TextStyle(color: Colors.grey, fontSize: 16)))
                  : _results.isEmpty
                      ? const Center(child: Text('No colleges found matching your criteria.', style: TextStyle(color: Colors.grey, fontSize: 16)))
                      : ListView.builder(
                          itemCount: _results.length,
                          itemBuilder: (context, index) {
                            final res = _results[index];
                            final prob = res['probability'] as double;
                            Color probColor = prob > 80 ? Colors.green : (prob > 40 ? Colors.orange : Colors.red);
                            
                            return Card(
                              margin: const EdgeInsets.only(bottom: 12),
                              child: Padding(
                                padding: const EdgeInsets.all(16),
                                child: Row(
                                  children: [
                                    Expanded(
                                      child: Column(
                                        crossAxisAlignment: CrossAxisAlignment.start,
                                        children: [
                                          Text(res['college'], style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16)),
                                          const SizedBox(height: 4),
                                          Text('${res['branch']} • ${res['city']}', style: const TextStyle(color: Colors.grey)),
                                          const SizedBox(height: 8),
                                          Text('Last Cutoff: ${res['last_cutoff']} (${res['year']}) | Category: ${res['category']}'),
                                        ],
                                      ),
                                    ),
                                    Container(
                                      padding: const EdgeInsets.all(12),
                                      decoration: BoxDecoration(
                                        color: probColor.withOpacity(0.1),
                                        borderRadius: BorderRadius.circular(8),
                                        border: Border.all(color: probColor),
                                      ),
                                      child: Column(
                                        children: [
                                          Text('${prob.toStringAsFixed(1)}%', style: TextStyle(color: probColor, fontWeight: FontWeight.bold, fontSize: 20)),
                                          Text('Chance', style: TextStyle(color: probColor, fontSize: 12)),
                                        ],
                                      ),
                                    )
                                  ],
                                ),
                              ),
                            );
                          },
                        ),
            ),
          ),
        ],
      ),
    );
  }
}
