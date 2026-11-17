import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:review/widgets/proxy_prompt_dialog.dart';
import '../theme/app_colors.dart';

class CurrencyOption {
  const CurrencyOption({
    required this.code,
    required this.name,
    required this.flag,
    required this.country,
  });

  final String code;
  final String name;
  final String flag;
  final String country;
}

const List<CurrencyOption> supportedCurrencies = [
  CurrencyOption(
    code: 'NGN',
    name: 'Nigerian Naira',
    flag: '🇳🇬',
    country: 'Nigeria',
  ),
  CurrencyOption(
    code: 'GHS',
    name: 'Ghanaian Cedi',
    flag: '🇬🇭',
    country: 'Ghana',
  ),
  CurrencyOption(
    code: 'KES',
    name: 'Kenyan Shilling',
    flag: '🇰🇪',
    country: 'Kenya',
  ),
  CurrencyOption(
    code: 'ZAR',
    name: 'South African Rand',
    flag: '🇿🇦',
    country: 'South Africa',
  ),
  CurrencyOption(
    code: 'GBP',
    name: 'British Pound Sterling',
    flag: '🇬🇧',
    country: 'United Kingdom',
  ),
  CurrencyOption(
    code: 'USD',
    name: 'US Dollar',
    flag: '🇺🇸',
    country: 'United States',
  ),
  CurrencyOption(
    code: 'CAD',
    name: 'Canadian Dollar',
    flag: '🇨🇦',
    country: 'Canada',
  ),
  CurrencyOption(
    code: 'ZMW',
    name: 'Zambian Kwacha',
    flag: '🇿🇲',
    country: 'Zambia',
  ),
  CurrencyOption(
    code: 'INR',
    name: 'Indian Rupee',
    flag: '🇮🇳',
    country: 'India',
  ),
  CurrencyOption(
    code: 'AED',
    name: 'UAE Dirham',
    flag: '🇦🇪',
    country: 'United Arab Emirates',
  ),
  CurrencyOption(
    code: 'AUD',
    name: 'Australian Dollar',
    flag: '🇦🇺',
    country: 'Australia',
  ),
  CurrencyOption(
    code: 'NZD',
    name: 'New Zealand Dollar',
    flag: '🇳🇿',
    country: 'New Zealand',
  ),
  CurrencyOption(
    code: 'UGX',
    name: 'Ugandan Shilling',
    flag: '🇺🇬',
    country: 'Uganda',
  ),
  CurrencyOption(
    code: 'TZS',
    name: 'Tanzanian Shilling',
    flag: '🇹🇿',
    country: 'Tanzania',
  ),
  CurrencyOption(
    code: 'RWF',
    name: 'Rwandan Franc',
    flag: '🇷🇼',
    country: 'Rwanda',
  ),
];

class CurrencyPickerContent extends StatefulWidget {
  const CurrencyPickerContent({
    required this.currencies,
    required this.current,
    required this.onSelect,
  });

  final List<CurrencyOption> currencies;
  final String current;
  final ValueChanged<String> onSelect;

  @override
  State<CurrencyPickerContent> createState() =>
      _CurrencyPickerContentState();
}

class _CurrencyPickerContentState
    extends State<CurrencyPickerContent> {
  final TextEditingController _searchController =
      TextEditingController();

  String _query = '';

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final colors = theme.colorScheme;

    final filteredCurrencies = widget.currencies.where((currency) {
      final query = _query.toLowerCase();

      return currency.code.toLowerCase().contains(query) ||
          currency.name.toLowerCase().contains(query) ||
          currency.country.toLowerCase().contains(query);
    }).toList();

    return SafeArea(
      child: Padding(
        padding: EdgeInsets.only(
          bottom: MediaQuery.of(context).viewInsets.bottom,
        ),
        child: SizedBox(
          height: MediaQuery.sizeOf(context).height * .75,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // Header
              Padding(
                padding: const EdgeInsets.fromLTRB(
                  20, 8, 20, 18,
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Container(
                          height: 46,
                          width: 46,
                          decoration: BoxDecoration(
                            color: colors.primaryContainer,
                            borderRadius: BorderRadius.circular(14),
                          ),
                          child: Icon(
                            Icons.currency_exchange_rounded,
                            color: colors.onPrimaryContainer,
                            size: 24,
                          ),
                        ),
                        const SizedBox(width: 12),
                        Expanded(
                          child: Column(
                            crossAxisAlignment:
                                CrossAxisAlignment.start,
                            children: [
                              Text(
                                'Preferred currency',
                                style: theme.textTheme.titleLarge
                                    ?.copyWith(
                                  fontWeight: FontWeight.w800,
                                ),
                              ),
                              const SizedBox(height: 3),
                              Text(
                                'Choose how you want amounts displayed.',
                                style: theme.textTheme.bodySmall
                                    ?.copyWith(
                                  color: colors.onSurfaceVariant,
                                ),
                              ),
                            ],
                          ),
                        ),
                      ],
                    ),

                    const SizedBox(height: 20),

                    // Search
                    TextField(
                      controller: _searchController,
                      onChanged: (value) {
                        setState(() {
                          _query = value.trim();
                        });
                      },
                      decoration: InputDecoration(
                        hintText: 'Search currency or country...',
                        prefixIcon: const Icon(
                          Icons.search_rounded,
                        ),
                        suffixIcon: _query.isNotEmpty
                            ? IconButton(
                                icon: const Icon(
                                  Icons.close_rounded,
                                ),
                                onPressed: () {
                                  _searchController.clear();
                                  setState(() => _query = '');
                                },
                              )
                            : null,
                        filled: true,
                        fillColor: colors.surfaceContainerHighest
                            .withValues(alpha: .55),
                        border: OutlineInputBorder(
                          borderRadius: BorderRadius.circular(14),
                          borderSide: BorderSide.none,
                        ),
                      ),
                    ),

                    const SizedBox(height: 14),

                    Text(
                      '${filteredCurrencies.length} currencies available',
                      style: theme.textTheme.labelMedium?.copyWith(
                        color: colors.onSurfaceVariant,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                  ],
                ),
              ),

              // Currency list
              Expanded(
                child: filteredCurrencies.isEmpty
                    ? Center(
                        child: Column(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            Icon(
                              Icons.search_off_rounded,
                              size: 42,
                              color: colors.onSurfaceVariant,
                            ),
                            const SizedBox(height: 12),
                            Text(
                              'No currencies found',
                              style: theme.textTheme.titleMedium,
                            ),
                            const SizedBox(height: 4),
                            Text(
                              'Try another currency or country name.',
                              style: theme.textTheme.bodySmall,
                            ),
                          ],
                        ),
                      )
                    : ListView.separated(
                        padding: const EdgeInsets.fromLTRB(
                          16, 0, 16, 20,
                        ),
                        itemCount: filteredCurrencies.length,
                        separatorBuilder: (_, __) =>
                            const SizedBox(height: 5),
                        itemBuilder: (context, index) {
                          final currency =
                              filteredCurrencies[index];

                          final isSelected =
                              currency.code == widget.current;

                          return Material(
                            color: isSelected
                                ? colors.primaryContainer
                                    .withValues(alpha: .55)
                                : colors.surface,
                            borderRadius: BorderRadius.circular(14),
                            child: InkWell(
                              borderRadius: BorderRadius.circular(14),
                              onTap: () {
                                widget.onSelect(currency.code);
                              },
                              child: Padding(
                                padding: const EdgeInsets.symmetric(
                                  horizontal: 12,
                                  vertical: 12,
                                ),
                                child: Row(
                                  children: [
                                    // Flag
                                    Container(
                                      width: 48,
                                      height: 48,
                                      alignment: Alignment.center,
                                      decoration: BoxDecoration(
                                        color: colors.surfaceContainerHighest
                                            .withValues(alpha: .6),
                                        borderRadius:
                                            BorderRadius.circular(13),
                                      ),
                                      child: Text(
                                        currency.flag,
                                        style: const TextStyle(
                                          fontSize: 27,
                                        ),
                                      ),
                                    ),

                                    const SizedBox(width: 13),

                                    // Currency information
                                    Expanded(
                                      child: Column(
                                        crossAxisAlignment:
                                            CrossAxisAlignment.start,
                                        children: [
                                          Text(
                                            currency.name,
                                            style: theme
                                                .textTheme.bodyLarge
                                                ?.copyWith(
                                              fontWeight:
                                                  FontWeight.w700,
                                            ),
                                          ),
                                          const SizedBox(height: 4),
                                          Text(
                                            '${currency.country}  •  ${currency.code}',
                                            style: theme
                                                .textTheme.bodySmall
                                                ?.copyWith(
                                              color: colors
                                                  .onSurfaceVariant,
                                            ),
                                          ),
                                        ],
                                      ),
                                    ),

                                    // Selection indicator
                                    if (isSelected)
                                      Icon(
                                        Icons.check_circle_rounded,
                                        color: colors.primary,
                                        size: 24,
                                      )
                                    else
                                      Icon(
                                        Icons.circle_outlined,
                                        color: colors.outlineVariant,
                                        size: 22,
                                      ),
                                  ],
                                ),
                              ),
                            ),
                          );
                        },
                      ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}


class StoreInfo {
  final String name;
  final String icon;
  final Color color;

  const StoreInfo({
    required this.name,
    required this.icon,
    required this.color,
  });
}

StoreInfo getStoreInfo(String? store) {
  if (store == null || store.isEmpty) {
    return const StoreInfo(
      name: 'App Store',
      icon: 'app_store',
      color: Colors.blueGrey,
    );
  }

  final normalized = store.toLowerCase();

  if (normalized.contains('google') || normalized.contains('play')) {
    return const StoreInfo(
      name: 'Google Play',
      icon: 'play_store',
      color: Color(0xFF34A853),
    );
  }

  if (normalized.contains('apple') || normalized.contains('ios')) {
    return const StoreInfo(
      name: 'App Store',
      icon: 'app_store',
      color: Color(0xFF555555),
    );
  }

  if (normalized.contains('samsung') || normalized.contains('galaxy')) {
    return const StoreInfo(
      name: 'Galaxy Store',
      icon: 'galaxy_store',
      color: Color(0xFF1428A0),
    );
  }

  return StoreInfo(
    name: store,
    icon: 'app_store',
    color: Colors.blueGrey,
  );
}

Color platformColor(String platform) {
  switch (platform.toLowerCase()) {
    case 'whatsapp':
      return const Color(0xFF25D366);

    case 'facebook':
      return const Color(0xFF1877F2);

    case 'instagram':
      return const Color(0xFFE4405F);

    case 'x':
      return const Color(0xFF202020);

    case 'tiktok':
      return const Color(0xFF161823);

    case 'youtube':
      return const Color(0xFFFF0033);

    case 'telegram':
      return const Color(0xFF229ED9);

    default:
      return AppColors.primary;
  }
}

  IconData platformIcon(String platform) {
    switch (platform) {
      case 'whatsapp':
        return Icons.chat_rounded;
      case 'facebook':
        return Icons.facebook_rounded;
      case 'instagram':
        return Icons.camera_alt_rounded;
      case 'x':
        return Icons.alternate_email_rounded;
      case 'tiktok':
        return Icons.music_note_rounded;
      case 'youtube':
        return Icons.play_arrow_rounded;
      case 'telegram':
        return Icons.send_rounded;
      default:
        return Icons.public_rounded;
    }
  }

Map<String, dynamic> _map(dynamic value) {
  if (value is Map<String, dynamic>) return value;
  if (value is Map) return Map<String, dynamic>.from(value);
  return {};
}

List<Map<String, dynamic>> _rows(dynamic value) {
  if (value is! List) return [];

  return value
      .whereType<Map>()
      .map((item) => Map<String, dynamic>.from(item))
      .toList();
}

Map<String, dynamic> applySnapshot(Map<String, dynamic> user) {
  final context = _map(user['subscription_context']);
  final catalog = _map(context['catalog']);

  final groups = _rows(catalog['subscription_groups']);

  final plans = groups.expand((group) {
    final groupKey = group['group_key']?.toString();

    return _rows(group['plans']).map((plan) {
      final item = Map<String, dynamic>.from(plan);
      item['group_key'] = groupKey;
      return item;
    });
  }).toList(growable: false);

  final result = {
    'user': user,
    'subscription_context': context,
    'catalog': catalog,
    'groups': groups,
    'plans': plans,
  };

  return result;
}

  Map<String, dynamic> addonInfo(Map<String, dynamic> record) =>
      _map(record['addon']);

  List<ProxyPromptFeature> proxyFeatures(Map<String, dynamic> record) {
    final addon = addonInfo(record);
    final features = _map(record['features']).isNotEmpty
        ? _map(record['features'])
        : _map(addon['features']);
    final output = <ProxyPromptFeature>[];

    final locations = features['location'] ?? features['country_code'];
    if (locations is List && locations.isNotEmpty) {
      final labels = locations.map((item) => item.toString()).join(', ');
      output.add(
        ProxyPromptFeature(
          label: 'Locations: $labels',
          icon: Icons.location_on_rounded,
        ),
      );
    }

    final bandwidth = features['bandwidth'];
    if (bandwidth is num && bandwidth > 0) {
      final gigabytes = bandwidth / 1000000000;
      final label = gigabytes >= 1
          ? '${gigabytes.toStringAsFixed(gigabytes >= 10 ? 0 : 1)} GB bandwidth'
          : '${(bandwidth / 1000000).round()} MB bandwidth';
      output.add(ProxyPromptFeature(label: label, icon: Icons.speed_rounded));
    }

    const switches = <(String, String, IconData)>[
      ('SSO_and_SCIM', 'SSO + SCIM', Icons.shield_outlined),
      ('email_support', 'Email support', Icons.email_outlined),
      ('phone_no', 'Phone support', Icons.phone_outlined),
      ('dedicated_ips', 'Dedicated IPs', Icons.dns_outlined),
      ('encrypted_tunnel', 'Encrypted tunnel', Icons.lock_outlined),
    ];
    for (final (key, label, icon) in switches) {
      if (features[key] == true || features[key] == 1) {
        output.add(ProxyPromptFeature(label: label, icon: icon));
      }
    }

    if (output.isEmpty) {
      output.add(
        const ProxyPromptFeature(
          label: 'Feature details unavailable',
          icon: Icons.info_outline_rounded,
        ),
      );
    }
    return output;
  }

  String getDuration(Map<String, dynamic> addon) {
    final interval = addon['billing_interval']?.toString().toLowerCase();
    final countValue = addon['duration_count'];
    final count = countValue is num
        ? countValue.toInt()
        : int.tryParse(countValue?.toString() ?? '') ?? 1;
    final unit = switch (interval) {
      'weekly' => 'week',
      'quarterly' => 'quarter',
      'yearly' || 'annual' => 'year',
      'monthly' || null => 'month',
      _ => interval ?? 'month',
    };
    return count == 1 ? '/$unit' : '/$count ${unit}s';
  }
