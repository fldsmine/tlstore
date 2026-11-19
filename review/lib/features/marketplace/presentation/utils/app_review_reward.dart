import '../../../../core/currency/money.dart';
import '../../../../services/auth_api.dart';

/// Returns the review reward configured on the system package for this app's
/// store. Users can see it before subscribing. Reserved tasks use their saved
/// reward in campaign currency so a resumed task retains its original amount.
String appReviewRewardText({
  required AuthApi api,
  required Map<String, dynamic> user,
  required Map<String, dynamic> listing,
  required String preferredCurrency,
}) {
  final currency = listing['currency']?.toString() ?? 'USD';
  final reservedReward = listing['reserved_reward_minor'];
  if (reservedReward is num) {
    return moneyConverted(
      api,
      reservedReward,
      currency,
      listing['currency_minor_units'],
      preferredCurrency,
      isMinor: true,
    );
  }

  final plan = _systemPlanForListing(user, listing, preferredCurrency, api);
  if (plan == null) return 'Reward not configured';

  final planCurrency = plan['currency']?.toString() ?? 'USD';
  final planReward = plan['task_reward_minor'];
  if (planReward is num) {
    return moneyConverted(
      api,
      planReward,
      planCurrency,
      plan['currency_minor_units'],
      preferredCurrency,
      isMinor: true,
    );
  }

  final features = _map(plan['features']);
  final perSurvey = features['per_survey'];
  if (perSurvey is num) {
    return moneyConverted(
      api,
      perSurvey,
      planCurrency,
      plan['currency_minor_units'],
      preferredCurrency,
      isMinor: false,
    );
  }

  return 'Reward set by plan';
}

Map<String, dynamic>? _systemPlanForListing(
  Map<String, dynamic> user,
  Map<String, dynamic> listing,
  String preferredCurrency,
  AuthApi api,
) {
  final app = _map(listing['app']);
  final store = app['store']?.toString() ?? '';
  final storeGroup = _storeGroup(store);
  if (storeGroup == null) return null;

  final subscriptionContext = _map(user['subscription_context']);
  final catalog = _map(subscriptionContext['catalog']);
  final groups = catalog['subscription_groups'];
  if (groups is! List) return null;

  final matches = <Map<String, dynamic>>[];
  for (final group in groups.whereType<Map>()) {
    final groupData = _map(group);
    if (groupData['group_key']?.toString() != storeGroup) continue;
    final plans = groupData['plans'];
    if (plans is! List) continue;
    for (final row in plans.whereType<Map>()) {
      final plan = Map<String, dynamic>.from(row);
      plan['group_key'] ??= storeGroup;
      if (_planMatchesListing(plan, app, storeGroup)) matches.add(plan);
    }
  }

  if (matches.isEmpty) return null;
  matches.sort((left, right) {
    final leftScore = _storeMatchScore(left, storeGroup, app);
    final rightScore = _storeMatchScore(right, storeGroup, app);
    if (leftScore != rightScore) return rightScore.compareTo(leftScore);

    final leftAmount = _rewardInPreferredCurrency(left, preferredCurrency, api);
    final rightAmount = _rewardInPreferredCurrency(right, preferredCurrency, api);
    return rightAmount.compareTo(leftAmount);
  });
  return matches.first;
}

bool _planMatchesListing(
  Map<String, dynamic> plan,
  Map<String, dynamic> app,
  String expectedGroup,
) {
  if (plan['group_key']?.toString() != expectedGroup) return false;
  final planName =
      '${plan['plan_key'] ?? ''} ${plan['name'] ?? ''}'.toLowerCase();
  if (expectedGroup == 'group_b' &&
      !_containsStoreName(planName, app['store']?.toString() ?? '')) {
    final eligibleStore = plan['eligible_app_store']?.toString().trim();
    if (eligibleStore == null || eligibleStore.isEmpty) return false;
  }
  final eligibleStore = plan['eligible_app_store']?.toString().trim();
  if (eligibleStore != null &&
      eligibleStore.isNotEmpty &&
      _storeKey(eligibleStore) != _storeKey(app['store']?.toString() ?? '')) {
    return false;
  }

  final eligibleCategory = plan['eligible_app_category']?.toString().trim();
  final appCategory = app['category']?.toString().trim();
  if (eligibleCategory != null &&
      eligibleCategory.isNotEmpty &&
      eligibleCategory.toLowerCase() != appCategory?.toLowerCase()) {
    return false;
  }

  return true;
}

bool _containsStoreName(String planName, String appStore) {
  final store = appStore.toLowerCase();
  if (store.contains('google') || store.contains('play')) {
    return planName.contains('google') || planName.contains('play');
  }
  if (store.contains('samsung') || store.contains('galaxy')) {
    return planName.contains('samsung') || planName.contains('galaxy');
  }
  if (store.contains('apple') || store.contains('ios')) {
    return planName.contains('apple') || planName.contains('ios');
  }
  return false;
}

int _storeMatchScore(
  Map<String, dynamic> plan,
  String groupKey,
  Map<String, dynamic> app,
) {
  final eligibleStore = plan['eligible_app_store']?.toString().trim();
  if (eligibleStore != null && eligibleStore.isNotEmpty) return 2;
  final planName =
      '${plan['plan_key'] ?? ''} ${plan['name'] ?? ''}'.toLowerCase();
  return _containsStoreName(planName, app['store']?.toString() ?? '') ? 1 : 0;
}

double _rewardInPreferredCurrency(
  Map<String, dynamic> plan,
  String preferredCurrency,
  AuthApi api,
) {
  final currency = plan['currency']?.toString() ?? 'USD';
  final minor = plan['task_reward_minor'];
  if (minor is num) {
    final displayed = moneyConverted(
      api,
      minor,
      currency,
      plan['currency_minor_units'],
      preferredCurrency,
      isMinor: true,
    );
    return _parseDisplayedAmount(displayed);
  }
  final perSurvey = _map(plan['features'])['per_survey'];
  if (perSurvey is num) {
    final displayed = moneyConverted(
      api,
      perSurvey,
      currency,
      plan['currency_minor_units'],
      preferredCurrency,
      isMinor: false,
    );
    return _parseDisplayedAmount(displayed);
  }
  return 0;
}

double _parseDisplayedAmount(String displayed) =>
    double.tryParse(displayed.replaceAll(RegExp(r'[^0-9.-]'), '')) ?? 0;

String? _storeGroup(String store) {
  final key = _storeKey(store);
  if (key == 'apple') return 'group_c';
  if (key == 'google' || key == 'samsung') return 'group_b';
  return null;
}

String? _storeKey(String store) {
  final value = store.toLowerCase();
  if (value.contains('apple') || value.contains('ios')) return 'apple';
  if (value.contains('google') || value.contains('play')) return 'google';
  if (value.contains('samsung') || value.contains('galaxy')) return 'samsung';
  return null;
}

Map<String, dynamic> _map(dynamic value) => value is Map
    ? Map<String, dynamic>.from(value)
    : <String, dynamic>{};
