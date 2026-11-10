import 'package:flutter/material.dart';

import '../../../../core/currency/money.dart';
import '../../../../services/auth_api.dart';
import '../../../../widgets/auth_components.dart';

class AccountTransactionCard extends StatelessWidget {
  const AccountTransactionCard({
    super.key,
    required this.transaction,
    required this.api,
    required this.preferredCurrency,
  });
  final Map<String, dynamic> transaction;
  final AuthApi api;
  final String preferredCurrency;

  @override
  Widget build(BuildContext context) {
    final currency = transaction['currency']?.toString() ?? 'USD';
    final digits = (transaction['currency_minor_units'] as num?)?.toInt() ?? 2;
    final amount = (transaction['amount_minor'] as num?)?.toInt() ?? 0;
    final credit = transaction['direction'] == 'credit';
    final date = DateTime.tryParse(transaction['created_at']?.toString() ?? '')
        ?.toLocal();
    final description = transaction['description']?.toString().trim() ?? '';
    final title = description.isNotEmpty
        ? description
        : _pretty(transaction['type']?.toString() ?? 'Transaction');

    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: Theme.of(context).cardColor,
        borderRadius: BorderRadius.circular(15),
        border: Border.all(color: const Color(0xFFE6EAE5)),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 43,
            height: 43,
            decoration: const BoxDecoration(
              color: softGreen,
              shape: BoxShape.circle,
            ),
            child: Icon(
              credit ? Icons.south_west_rounded : Icons.north_east_rounded,
              color: brandGreen,
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    fontWeight: FontWeight.w800,
                    color: ink,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  '${_pretty(transaction['status']?.toString() ?? 'completed')}'
                  '${date == null ? '' : '  •  ${date.day}/${date.month}/${date.year}'}',
                  style: const TextStyle(color: mutedInk, fontSize: 11),
                ),
                if (transaction['wallet'] != null)
                  Padding(
                    padding: const EdgeInsets.only(top: 3),
                    child: Text(
                      '${_pretty(transaction['wallet'].toString())} wallet',
                      style: const TextStyle(color: mutedInk, fontSize: 11),
                    ),
                  ),
              ],
            ),
          ),
          const SizedBox(width: 8),
          Text(
            '${credit ? '+' : '−'}${moneyConverted(api, amount, currency, digits, preferredCurrency, isMinor: true)}',
            style: TextStyle(
              color: credit ? brandGreen : const Color(0xFF5B6672),
              fontWeight: FontWeight.w800,
              fontSize: 13,
            ),
          ),
        ],
      ),
    );
  }
}

String _pretty(String value) => value
    .replaceAll('_', ' ')
    .split(' ')
    .map(
      (word) =>
          word.isEmpty ? word : '${word[0].toUpperCase()}${word.substring(1)}',
    )
    .join(' ');

String _formatMinor(int amount, String currency, int digits) {
  const symbols = {'USD': r'$', 'NGN': '₦', 'GBP': '£', 'EUR': '€', 'GHS': '₵'};
  final factor = digits == 0
      ? 1
      : digits == 3
      ? 1000
      : 100;
  return '${symbols[currency] ?? '$currency '}${(amount / factor).toStringAsFixed(digits)}';
}
