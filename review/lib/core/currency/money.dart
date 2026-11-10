import 'dart:math' as math;

import '../../services/auth_api.dart';

int currencyPrecision(String currency) {
  final code = currency.toUpperCase();
  if (const {
    'BIF',
    'CLP',
    'DJF',
    'GNF',
    'ISK',
    'JPY',
    'KMF',
    'KRW',
    'PYG',
    'RWF',
    'UGX',
    'VND',
    'VUV',
    'XAF',
    'XOF',
    'XPF',
  }.contains(code))
    return 0;
  if (const {'BHD', 'IQD', 'JOD', 'KWD', 'LYD', 'OMR', 'TND'}.contains(code))
    return 3;
  return 2;
}

String moneyConverted(
  AuthApi? api,
  dynamic amount,
  String sourceCurrency,
  dynamic precision,
  String preferredCurrency, {
  required bool isMinor,
}) {
  final source = sourceCurrency.toUpperCase();
  final target = preferredCurrency.toUpperCase();
  final digits = (precision as num?)?.toInt() ?? currencyPrecision(source);
  final raw = double.tryParse(amount?.toString() ?? '') ?? 0;
  final major = isMinor ? raw / math.pow(10, digits) : raw;
  final converted = api?.convertMajor(major, source, target);
  final displayCurrency = converted == null ? source : target;
  final displayAmount = converted ?? major;
  return formatMajorMoney(displayAmount, displayCurrency);
}

String formatMajorMoney(double amount, String currency) {
  final code = currency.toUpperCase();
  const symbols = <String, String>{
    'USD': r'$',
    'NGN': '₦',
    'GBP': '£',
    'EUR': '€',
    'GHS': '₵',
    'ZAR': 'R',
    'KES': 'KSh',
    'INR': '₹',
    'JPY': '¥',
  };
  return '${symbols[code] ?? '$code '}${amount.toStringAsFixed(currencyPrecision(code))}';
}
