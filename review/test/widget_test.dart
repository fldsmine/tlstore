import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:review/services/auth_api.dart';
import 'package:review/widgets/task_completion_page.dart';

void main() {
  testWidgets('task completion page shows the referral promotion', (
    tester,
  ) async {
    final api = AuthApi(
      client: MockClient(
        (_) async => http.Response(
          jsonEncode({
            'data': {'code': 'SAM123'},
          }),
          200,
          headers: {'content-type': 'application/json'},
        ),
      ),
    );

    await tester.pumpWidget(
      MaterialApp(
        home: TaskCompletionPage(
          api: api,
          token: 'test-token',
          user: const {'full_name': 'Sam Tester'},
          taskLabel: 'App review',
        ),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('Nice work, Sam!'), findsOneWidget);
    expect(find.text('What’s next?'), findsOneWidget);
    expect(find.text('Earn extra by inviting friends'), findsOneWidget);
    expect(find.text('Share with friends'), findsOneWidget);
    expect(find.textContaining('SAM123'), findsOneWidget);
  });
}
