import 'package:flutter/material.dart';
import 'package:razorpay_flutter/razorpay_flutter.dart';
import 'package:go_router/go_router.dart';
import 'package:http/http.dart' as http;
import 'dart:convert';
import 'package:supabase_flutter/supabase_flutter.dart';
import '../services/student_service.dart';

class SubscriptionScreen extends StatefulWidget {
  const SubscriptionScreen({super.key});

  @override
  State<SubscriptionScreen> createState() => _SubscriptionScreenState();
}

class _SubscriptionScreenState extends State<SubscriptionScreen> {
  late Razorpay _razorpay;
  bool _isProcessing = false;
  String _currentPlan = 'free';

  final Map<String, dynamic> _plans = {
    'basic': {
      'name': 'Basic',
      'price': 149,
      'features': [
        'Full Mock Tests',
        'Rapid Fire & Subject Tests',
        'Weekly Challenges',
        'Community Read Access',
        'Basic Analytics',
        '50 AI Tutor Requests/mo'
      ]
    },
    'pro': {
      'name': 'Pro',
      'price': 299,
      'features': [
        'Everything in Basic',
        'Previous Year Papers',
        'Revision Mode',
        'Challenge a Friend',
        'Full Analytics & PDF Reports',
        'Unlimited AI Explanations',
        'Leaderboard Access',
        'Priority Support'
      ]
    }
  };

  @override
  void initState() {
    super.initState();
    _razorpay = Razorpay();
    _razorpay.on(Razorpay.EVENT_PAYMENT_SUCCESS, _handlePaymentSuccess);
    _razorpay.on(Razorpay.EVENT_PAYMENT_ERROR, _handlePaymentError);
    _razorpay.on(Razorpay.EVENT_EXTERNAL_WALLET, _handleExternalWallet);
    _loadSubscription();
  }

  Future<void> _loadSubscription() async {
    final studentId = await StudentService.getStudentId();
    if (studentId != null) {
      final res = await Supabase.instance.client
          .from('subscriptions')
          .select('plan,status,expires_at')
          .eq('student_id', studentId)
          .eq('status', 'active')
          .gte('expires_at', DateTime.now().toIso8601String())
          .limit(1);
          
      if (res.isNotEmpty) {
        setState(() {
          _currentPlan = res[0]['plan'];
        });
      }
    }
  }

  @override
  void dispose() {
    super.dispose();
    _razorpay.clear();
  }

  Future<void> _startCheckout(String planId, int price) async {
    setState(() => _isProcessing = true);
    
    try {
      final studentId = await StudentService.getStudentId();
      if (studentId == null) throw Exception("Not logged in");

      // Note: Calling PHP backend to securely generate Razorpay Order
      final response = await http.post(
        Uri.parse('https://ddcetprep.onrender.com/api/create_order.php'), // Update to your API URL
        body: {'plan': planId},
        headers: {
           'X-Student-Id': studentId.toString(),
        }
      );

      final data = jsonDecode(response.body);
      if (data['success'] == true) {
        var options = {
          'key': 'rzp_test_YourKey', // replace with real Razorpay key
          'amount': price * 100, // in paise
          'name': 'DDCET Prep',
          'description': '${_plans[planId]['name']} Subscription',
          'order_id': data['order_id'],
          'prefill': {
            'contact': user.phone ?? '',
            'email': user.email ?? ''
          },
          'theme': {
            'color': '#4361EE'
          }
        };

        _razorpay.open(options);
      } else {
        throw Exception(data['error'] ?? "Failed to create order");
      }
    } catch (e) {
      setState(() => _isProcessing = false);
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('Error: $e')));
    }
  }

  void _handlePaymentSuccess(PaymentSuccessResponse response) {
    setState(() => _isProcessing = false);
    ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text("Payment Successful!"), backgroundColor: Colors.green));
    _loadSubscription(); // reload to get new plan
  }

  void _handlePaymentError(PaymentFailureResponse response) {
    setState(() => _isProcessing = false);
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text("Payment Failed: ${response.message}"), backgroundColor: Colors.red));
  }

  void _handleExternalWallet(ExternalWalletResponse response) {
    setState(() => _isProcessing = false);
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text("External Wallet Selected: ${response.walletName}")));
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFF8F9FA),
      appBar: AppBar(
        title: const Text('Upgrade Plan', style: TextStyle(fontWeight: FontWeight.bold)),
      ),
      body: _isProcessing
          ? const Center(child: CircularProgressIndicator())
          : SingleChildScrollView(
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Text('Choose Your Plan', style: TextStyle(fontSize: 24, fontWeight: FontWeight.bold), textAlign: TextAlign.center),
                  const SizedBox(height: 8),
                  const Text('Unlock all features and ace the DDCET.', style: TextStyle(color: Colors.grey, fontSize: 14), textAlign: TextAlign.center),
                  const SizedBox(height: 32),
                  
                  _buildPlanCard('basic', _plans['basic']),
                  const SizedBox(height: 16),
                  _buildPlanCard('pro', _plans['pro'], isHighlighted: true),
                ],
              ),
            ),
    );
  }

  Widget _buildPlanCard(String id, Map<String, dynamic> plan, {bool isHighlighted = false}) {
    bool isCurrent = _currentPlan == id;
    
    return Container(
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: isHighlighted ? const Color(0xFF4361EE) : const Color(0xFFE2E8F0), width: isHighlighted ? 2 : 1),
        boxShadow: isHighlighted ? [BoxShadow(color: const Color(0xFF4361EE).withOpacity(0.2), blurRadius: 10, offset: const Offset(0, 4))] : [],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          if (isHighlighted)
            Container(
              padding: const EdgeInsets.symmetric(vertical: 6),
              decoration: const BoxDecoration(
                color: Color(0xFF4361EE),
                borderRadius: BorderRadius.only(topLeft: Radius.circular(14), topRight: Radius.circular(14)),
              ),
              child: const Text('RECOMMENDED', textAlign: TextAlign.center, style: TextStyle(color: Colors.white, fontSize: 11, fontWeight: FontWeight.bold, letterSpacing: 1)),
            ),
          Padding(
            padding: const EdgeInsets.all(24),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(plan['name'], style: const TextStyle(fontSize: 20, fontWeight: FontWeight.bold)),
                const SizedBox(height: 8),
                Row(
                  crossAxisAlignment: CrossAxisAlignment.baseline,
                  textBaseline: TextBaseline.alphabetic,
                  children: [
                    const Text('₹', style: TextStyle(fontSize: 20, fontWeight: FontWeight.bold)),
                    Text('${plan['price']}', style: const TextStyle(fontSize: 36, fontWeight: FontWeight.w900)),
                    const Text('/year', style: TextStyle(fontSize: 14, color: Colors.grey)),
                  ],
                ),
                const SizedBox(height: 24),
                
                ...List.generate(plan['features'].length, (i) {
                  return Padding(
                    padding: const EdgeInsets.only(bottom: 12),
                    child: Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const Icon(Icons.check_circle, color: Colors.green, size: 20),
                        const SizedBox(width: 12),
                        Expanded(child: Text(plan['features'][i], style: const TextStyle(fontSize: 14))),
                      ],
                    ),
                  );
                }),
                
                const SizedBox(height: 24),
                ElevatedButton(
                  onPressed: isCurrent ? null : () => _startCheckout(id, plan['price']),
                  style: ElevatedButton.styleFrom(
                    backgroundColor: isHighlighted ? const Color(0xFF4361EE) : Colors.white,
                    foregroundColor: isHighlighted ? Colors.white : const Color(0xFF4361EE),
                    side: isHighlighted ? null : const BorderSide(color: Color(0xFF4361EE)),
                    padding: const EdgeInsets.symmetric(vertical: 16),
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                  ),
                  child: Text(isCurrent ? 'Current Plan' : 'Subscribe Now'),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
