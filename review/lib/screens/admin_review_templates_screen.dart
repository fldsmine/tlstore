import 'package:flutter/material.dart';

import '../services/auth_api.dart';

// ---------------------------------------------------------------------------
// Design language (mirrors the app listings console so both admin surfaces
// feel like one product)
// ---------------------------------------------------------------------------

abstract final class _Palette {
  static const Color indigo = Color(0xFF4338CA);
  static const Color violet = Color(0xFF7C3AED);
  static const Color pink = Color(0xFFEC4899);
  static const Color green = Color(0xFF16A34A);
  static const Color teal = Color(0xFF0D9488);
  static const Color amber = Color(0xFFF59E0B);
  static const Color orange = Color(0xFFF97316);
  static const Color sky = Color(0xFF0284C7);
  static const Color rose = Color(0xFFE11D48);

  static const LinearGradient hero = LinearGradient(
    begin: Alignment.topLeft,
    end: Alignment.bottomRight,
    colors: <Color>[Color(0xFF3730A3), Color(0xFF7C3AED), Color(0xFFEC4899)],
  );

  static const LinearGradient mint = LinearGradient(
    begin: Alignment.topLeft,
    end: Alignment.bottomRight,
    colors: <Color>[Color(0xFF0D9488), Color(0xFF22C55E)],
  );

  static const LinearGradient sunset = LinearGradient(
    begin: Alignment.topLeft,
    end: Alignment.bottomRight,
    colors: <Color>[Color(0xFFF59E0B), Color(0xFFF97316)],
  );

  static const LinearGradient ocean = LinearGradient(
    begin: Alignment.topLeft,
    end: Alignment.bottomRight,
    colors: <Color>[Color(0xFF0284C7), Color(0xFF4338CA)],
  );

  static const LinearGradient berry = LinearGradient(
    begin: Alignment.topLeft,
    end: Alignment.bottomRight,
    colors: <Color>[Color(0xFFEC4899), Color(0xFF7C3AED)],
  );
}

ThemeData _adminTheme(BuildContext context) {
  final ThemeData base = Theme.of(context);
  return base.copyWith(
    colorScheme: ColorScheme.fromSeed(
      seedColor: _Palette.indigo,
      brightness: base.brightness,
    ),
  );
}

class _TypeMeta {
  const _TypeMeta(this.label, this.icon, this.color);

  final String label;
  final IconData icon;
  final Color color;
}

const Map<String, _TypeMeta> _typeMeta = <String, _TypeMeta>{
  'single_choice': _TypeMeta(
    'Single choice',
    Icons.radio_button_checked_rounded,
    _Palette.indigo,
  ),
  'multiple_choice': _TypeMeta(
    'Multiple choice',
    Icons.checklist_rounded,
    _Palette.violet,
  ),
  'rating': _TypeMeta('Rating (1–5)', Icons.star_rounded, _Palette.amber),
  'long_text': _TypeMeta('Long text', Icons.text_fields_rounded, _Palette.teal),
  'confirmation': _TypeMeta(
    'Confirmation',
    Icons.task_alt_rounded,
    _Palette.green,
  ),
};

_TypeMeta _metaFor(String? type) =>
    _typeMeta[type] ??
    const _TypeMeta('Unknown type', Icons.help_outline_rounded, _Palette.sky);

Color _statusColor(String status) => switch (status) {
  'active' => _Palette.green,
  'published' => _Palette.green,
  'rejected' => _Palette.rose,
  'draft' => _Palette.amber,
  'archived' => _Palette.sky,
  _ => _Palette.violet,
};

IconData _statusIcon(String status) => switch (status) {
  'active' => Icons.bolt_rounded,
  'published' => Icons.verified_rounded,
  'rejected' => Icons.block_rounded,
  'draft' => Icons.edit_note_rounded,
  'archived' => Icons.inventory_2_rounded,
  _ => Icons.hourglass_bottom_rounded,
};

String? _required(String? value) =>
    value == null || value.trim().isEmpty ? 'Required' : null;

Map<String, dynamic> _map(dynamic value) =>
    value is Map ? Map<String, dynamic>.from(value) : <String, dynamic>{};

/// Snackbar helper that takes an already-resolved messenger so it can safely
/// run after an `await`.
void _showToast(
  ScaffoldMessengerState messenger,
  String text, {
  bool error = false,
  bool success = false,
}) {
  messenger
    ..hideCurrentSnackBar()
    ..showSnackBar(
      SnackBar(
        behavior: SnackBarBehavior.floating,
        backgroundColor: error
            ? _Palette.rose
            : success
            ? _Palette.green
            : _Palette.indigo,
        margin: const EdgeInsets.fromLTRB(14, 0, 14, 18),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
        content: Row(
          children: <Widget>[
            Icon(
              error
                  ? Icons.error_outline_rounded
                  : success
                  ? Icons.check_circle_outline_rounded
                  : Icons.info_outline_rounded,
              color: Colors.white,
              size: 20,
            ),
            const SizedBox(width: 10),
            Expanded(
              child: Text(
                text,
                style: const TextStyle(
                  color: Colors.white,
                  fontWeight: FontWeight.w600,
                ),
              ),
            ),
          ],
        ),
      ),
    );
}

// ---------------------------------------------------------------------------
// Screen
// ---------------------------------------------------------------------------

class AdminReviewTemplatesScreen extends StatefulWidget {
  const AdminReviewTemplatesScreen({super.key, required this.api});

  final AuthApi api;

  @override
  State<AdminReviewTemplatesScreen> createState() =>
      _AdminReviewTemplatesScreenState();
}

class _AdminReviewTemplatesScreenState extends State<AdminReviewTemplatesScreen>
    with SingleTickerProviderStateMixin {
  final TextEditingController _email = TextEditingController(
    text: 'admin@tlstore.test',
  );
  final TextEditingController _password = TextEditingController(
    text: 'meandmyson',
  );
  final TextEditingController _templateSearch = TextEditingController();
  final TextEditingController _questionSearch = TextEditingController();
  late final TabController _tabs = TabController(length: 2, vsync: this);

  String? _token;
  String? _error;
  bool _loading = false;
  List<Map<String, dynamic>> _templates = <Map<String, dynamic>>[];
  List<Map<String, dynamic>> _questions = <Map<String, dynamic>>[];
  bool _obscurePassword = true;
  String _templateQuery = '';
  String _questionQuery = '';
  String _typeFilter = 'all';

  @override
  void dispose() {
    _email.dispose();
    _password.dispose();
    _templateSearch.dispose();
    _questionSearch.dispose();
    _tabs.dispose();
    super.dispose();
  }

  // -- data --------------------------------------------------------------

  List<Map<String, dynamic>> _rows(dynamic value) {
    final dynamic data = _map(value)['data'] ?? value;
    return data is List ? data.map(_map).toList() : <Map<String, dynamic>>[];
  }

  List<Map<String, dynamic>> get _visibleTemplates {
    final String needle = _templateQuery.trim().toLowerCase();
    return _templates.where((Map<String, dynamic> template) {
      if (needle.isEmpty) return true;
      return '${template['name']} ${template['template_key']} '
              '${template['status']}'
          .toLowerCase()
          .contains(needle);
    }).toList();
  }

  List<Map<String, dynamic>> get _visibleQuestions {
    final String needle = _questionQuery.trim().toLowerCase();
    return _questions.where((Map<String, dynamic> question) {
      final String type = question['type']?.toString() ?? '';
      if (_typeFilter != 'all' && type != _typeFilter) return false;
      if (needle.isEmpty) return true;
      return '${question['prompt']} ${question['step_name']} '
              '${question['question_key']}'
          .toLowerCase()
          .contains(needle);
    }).toList();
  }

  int get _publishedVersionCount {
    int count = 0;
    for (final Map<String, dynamic> template in _templates) {
      for (final Map<String, dynamic> version in _versionRows(template)) {
        if (version['status'] == 'published') count++;
      }
    }
    return count;
  }

  int get _lockedQuestionCount =>
      _questions.where(_isQuestionLocked).length;

  List<Map<String, dynamic>> _versionRows(Map<String, dynamic> template) =>
      template['versions'] is List
      ? (template['versions'] as List).map(_map).toList()
      : <Map<String, dynamic>>[];

  bool _isQuestionLocked(Map<String, dynamic> question) {
    final dynamic links = question['template_version_links'];
    if (links is! List) return false;
    return links.any(
      (dynamic link) =>
          _map(_map(link)['template_version'])['status'] == 'published',
    );
  }

  Future<void> _login() async {
    final ScaffoldMessengerState messenger = ScaffoldMessenger.of(context);
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final Map<String, dynamic> response = await widget.api.postPublic(
        'admin/auth/login',
        <String, dynamic>{
          'email': _email.text.trim(),
          'password': _password.text,
        },
      );
      final String? token = response['token']?.toString();
      if (token == null || token.isEmpty) {
        throw const AuthException('Admin sign-in did not return a token.');
      }
      _token = token;
      await _load();
    } on AuthException catch (error) {
      if (mounted) setState(() => _error = error.message);
    } finally {
      if (mounted) setState(() => _loading = false);
    }
    if (!mounted) return;
    if (_token != null && _error == null) {
      _showToast(messenger, 'Signed in. Questionnaires are up to date.');
    }
  }

  Future<void> _load({bool announce = false}) async {
    final String? token = _token;
    if (token == null) return;
    final ScaffoldMessengerState messenger = ScaffoldMessenger.of(context);
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final List<dynamic> results = await Future.wait(<Future<dynamic>>[
        widget.api.get('admin/review-templates?per_page=100', token),
        widget.api.get('admin/review-questions?per_page=100', token),
      ]);
      if (!mounted) return;
      setState(() {
        _templates = _rows(results[0]['data']);
        _questions = _rows(results[1]['data']);
      });
    } on AuthException catch (error) {
      if (mounted) setState(() => _error = error.message);
    } finally {
      if (mounted) setState(() => _loading = false);
    }
    if (!mounted || !announce) return;
    if (_error == null) {
      _showToast(
        messenger,
        'Refreshed ${_templates.length} template(s) and '
        '${_questions.length} question(s).',
      );
    }
  }

  void _signOut() {
    setState(() {
      _token = null;
      _templates = <Map<String, dynamic>>[];
      _questions = <Map<String, dynamic>>[];
      _error = null;
      _templateQuery = '';
      _questionQuery = '';
      _typeFilter = 'all';
      _templateSearch.clear();
      _questionSearch.clear();
    });
    _tabs.animateTo(0);
  }

  // -- actions -----------------------------------------------------------

  Future<void> _openQuestionEditor({Map<String, dynamic>? existing}) async {
    final String? token = _token;
    if (token == null) return;
    final bool? saved = await showDialog<bool>(
      context: context,
      barrierColor: Colors.black.withValues(alpha: .55),
      // Routes pushed by showDialog sit above this screen's Theme widget,
      // so the admin palette is reapplied inside each route.
      builder: (BuildContext dialogContext) => Theme(
        data: _adminTheme(dialogContext),
        child: _QuestionEditor(
          api: widget.api,
          token: token,
          existing: existing,
        ),
      ),
    );
    if (saved == true) await _load();
  }

  Future<void> _newTemplate() async {
    final String? token = _token;
    if (token == null) return;
    final bool? saved = await showDialog<bool>(
      context: context,
      barrierColor: Colors.black.withValues(alpha: .55),
      builder: (BuildContext dialogContext) => Theme(
        data: _adminTheme(dialogContext),
        child: _TemplateDialog(api: widget.api, token: token),
      ),
    );
    if (saved == true) await _load();
  }

  Future<void> _newVersion(Map<String, dynamic> template) async {
    final String? token = _token;
    if (token == null) return;
    if (_questions.isEmpty) {
      _showToast(
        ScaffoldMessenger.of(context),
        'Create questions before building a template version.',
      );
      _tabs.animateTo(1);
      return;
    }
    final bool? saved = await showDialog<bool>(
      context: context,
      barrierColor: Colors.black.withValues(alpha: .55),
      builder: (BuildContext dialogContext) => Theme(
        data: _adminTheme(dialogContext),
        child: _VersionBuilderDialog(
          api: widget.api,
          token: token,
          template: template,
          questions: _questions,
        ),
      ),
    );
    if (saved == true) await _load();
  }

  Future<void> _publish(
    Map<String, dynamic> template,
    Map<String, dynamic> version,
  ) async {
    final String? token = _token;
    if (token == null) return;
    final ScaffoldMessengerState messenger = ScaffoldMessenger.of(context);
    final bool? confirm = await showDialog<bool>(
      context: context,
      builder: (BuildContext dialogContext) => Theme(
        data: _adminTheme(dialogContext),
        child: _ConfirmDialog(
          title: 'Publish questionnaire?',
          icon: Icons.lock_rounded,
          gradient: _Palette.mint,
          message:
              'Version ${version['version']} of '
              '${template['name'] ?? 'this template'} will be locked and can '
              'then be assigned to app listings. Published versions cannot be '
              'edited.',
          confirmLabel: 'Publish version',
          cancelLabel: 'Not yet',
        ),
      ),
    );
    if (confirm != true) return;
    try {
      await widget.api.post(
        'admin/review-templates/${template['id']}/versions/${version['id']}/publish',
        token,
        const <String, dynamic>{},
      );
      await _load();
      _showToast(
        messenger,
        'Version ${version['version']} is now published.',
        success: true,
      );
    } on AuthException catch (error) {
      _showToast(messenger, error.message, error: true);
    }
  }
  // -- build -------------------------------------------------------------

  @override
  Widget build(BuildContext context) {
    return Theme(
      data: _adminTheme(context),
      child: Builder(
        builder: (BuildContext context) => _token == null
            ? _buildSignIn(context)
            : _buildDashboard(context),
      ),
    );
  }

  Widget _buildSignIn(BuildContext context) {
    final ThemeData theme = Theme.of(context);
    return Scaffold(
      body: DecoratedBox(
        decoration: const BoxDecoration(gradient: _Palette.hero),
        child: SafeArea(
          child: Center(
            child: SingleChildScrollView(
              padding: const EdgeInsets.all(24),
              child: ConstrainedBox(
                constraints: const BoxConstraints(maxWidth: 440),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: <Widget>[
                    Container(
                      width: 76,
                      height: 76,
                      decoration: BoxDecoration(
                        color: Colors.white.withValues(alpha: .18),
                        shape: BoxShape.circle,
                        border: Border.all(
                          color: Colors.white.withValues(alpha: .35),
                        ),
                      ),
                      child: const Icon(
                        Icons.fact_check_rounded,
                        size: 38,
                        color: Colors.white,
                      ),
                    ),
                    const SizedBox(height: 18),
                    const Text(
                      'Review questionnaire studio',
                      textAlign: TextAlign.center,
                      style: TextStyle(
                        color: Colors.white,
                        fontSize: 26,
                        fontWeight: FontWeight.w800,
                        letterSpacing: -.4,
                      ),
                    ),
                    const SizedBox(height: 6),
                    Text(
                      'Build the question bank, assemble template versions and '
                      'publish the questionnaire reviewers answer.',
                      textAlign: TextAlign.center,
                      style: TextStyle(
                        color: Colors.white.withValues(alpha: .85),
                        height: 1.4,
                      ),
                    ),
                    const SizedBox(height: 24),
                    Container(
                      padding: const EdgeInsets.all(20),
                      decoration: BoxDecoration(
                        color: theme.colorScheme.surface,
                        borderRadius: BorderRadius.circular(26),
                        boxShadow: <BoxShadow>[
                          BoxShadow(
                            color: Colors.black.withValues(alpha: .22),
                            blurRadius: 30,
                            offset: const Offset(0, 18),
                          ),
                        ],
                      ),
                      child: Column(
                        mainAxisSize: MainAxisSize.min,
                        crossAxisAlignment: CrossAxisAlignment.stretch,
                        children: <Widget>[
                          _SignInField(
                            controller: _email,
                            label: 'Admin email',
                            icon: Icons.alternate_email_rounded,
                            keyboard: TextInputType.emailAddress,
                          ),
                          const SizedBox(height: 12),
                          _SignInField(
                            controller: _password,
                            label: 'Password',
                            icon: Icons.lock_outline_rounded,
                            obscure: _obscurePassword,
                            onSubmitted: (_) => _login(),
                            suffix: IconButton(
                              tooltip: _obscurePassword
                                  ? 'Show password'
                                  : 'Hide password',
                              onPressed: () => setState(
                                () => _obscurePassword = !_obscurePassword,
                              ),
                              icon: Icon(
                                _obscurePassword
                                    ? Icons.visibility_outlined
                                    : Icons.visibility_off_outlined,
                                size: 20,
                              ),
                            ),
                          ),
                          if (_error != null) ...<Widget>[
                            const SizedBox(height: 14),
                            _ErrorBanner(message: _error!),
                          ],
                          const SizedBox(height: 18),
                          _GradientButton(
                            label: _loading ? 'Signing in…' : 'Sign in',
                            icon: _loading
                                ? null
                                : Icons.arrow_forward_rounded,
                            busy: _loading,
                            onPressed: _loading ? null : _login,
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(height: 18),
                    Text(
                      'Credentials are pre-filled for the test environment.',
                      style: TextStyle(
                        color: Colors.white.withValues(alpha: .8),
                        fontSize: 12,
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }

  Widget _buildDashboard(BuildContext context) {
    final ThemeData theme = Theme.of(context);
    return Scaffold(
      backgroundColor: theme.colorScheme.surface,
      appBar: AppBar(
        elevation: 0,
        scrolledUnderElevation: 0,
        backgroundColor: _Palette.indigo,
        foregroundColor: Colors.white,
        centerTitle: false,
        flexibleSpace: const DecoratedBox(
          decoration: BoxDecoration(gradient: _Palette.hero),
        ),
        title: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: <Widget>[
            const Text(
              'Review questions',
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: TextStyle(
                color: Colors.white,
                fontWeight: FontWeight.w800,
                letterSpacing: -.3,
              ),
            ),
            Text(
              '${_templates.length} templates · ${_questions.length} '
              'questions · $_publishedVersionCount published',
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: TextStyle(
                color: Colors.white.withValues(alpha: .85),
                fontSize: 11.5,
                fontWeight: FontWeight.w600,
              ),
            ),
          ],
        ),
        actions: <Widget>[
          _AppBarAction(
            icon: Icons.refresh_rounded,
            tooltip: 'Refresh data',
            onPressed: _loading ? null : () => _load(announce: true),
          ),
          _AppBarAction(
            icon: Icons.logout_rounded,
            tooltip: 'Sign out',
            onPressed: _signOut,
          ),
          const SizedBox(width: 4),
        ],
        bottom: TabBar(
          controller: _tabs,
          labelColor: Colors.white,
          unselectedLabelColor: Colors.white70,
          indicatorColor: Colors.white,
          indicatorSize: TabBarIndicatorSize.tab,
          indicatorWeight: 3,
          dividerColor: Colors.transparent,
          labelStyle: const TextStyle(
            fontWeight: FontWeight.w800,
            fontSize: 13.5,
          ),
          unselectedLabelStyle: const TextStyle(
            fontWeight: FontWeight.w600,
            fontSize: 13.5,
          ),
          tabs: const <Widget>[
            Tab(
              icon: Icon(Icons.folder_special_rounded, size: 18),
              text: 'Templates',
            ),
            Tab(
              icon: Icon(Icons.quiz_rounded, size: 18),
              text: 'Question bank',
            ),
          ],
        ),
      ),
      floatingActionButton: AnimatedBuilder(
        animation: _tabs,
        builder: (BuildContext context, Widget? child) {
          final bool onTemplates = _tabs.index == 0;
          return _GradientFab(
            icon: onTemplates
                ? Icons.library_add_rounded
                : Icons.add_rounded,
            label: onTemplates ? 'New template' : 'New question',
            onPressed: onTemplates
                ? _newTemplate
                : () => _openQuestionEditor(),
          );
        },
      ),
      body: Column(
        children: <Widget>[
          AnimatedBuilder(
            animation: _tabs,
            builder: (BuildContext context, Widget? child) =>
                _buildTabHeader(context),
          ),
          Expanded(
            child: RefreshIndicator(
              color: Colors.white,
              backgroundColor: _Palette.indigo,
              onRefresh: () => _load(announce: true),
              child: TabBarView(
                controller: _tabs,
                children: <Widget>[_templatesTab(), _questionsTab()],
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildTabHeader(BuildContext context) {
    final bool onTemplates = _tabs.index == 0;
    int countByType(String type) => _questions
        .where((Map<String, dynamic> question) =>
            question['type']?.toString() == type)
        .length;

    return Container(
      padding: const EdgeInsets.fromLTRB(16, 14, 16, 10),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: <Widget>[
          _TileRow(
            minItemWidth: 150,
            children: <Widget>[
              _StatTile(
                label: 'Templates',
                value: '${_templates.length}',
                icon: Icons.folder_special_rounded,
                gradient: _Palette.ocean,
              ),
              _StatTile(
                label: 'Published versions',
                value: '$_publishedVersionCount',
                icon: Icons.verified_rounded,
                gradient: _Palette.mint,
              ),
              _StatTile(
                label: 'Questions',
                value: '${_questions.length}',
                icon: Icons.quiz_rounded,
                gradient: _Palette.berry,
              ),
              _StatTile(
                label: 'Locked in use',
                value: '$_lockedQuestionCount',
                icon: Icons.lock_rounded,
                gradient: _Palette.sunset,
              ),
            ],
          ),
          const SizedBox(height: 12),
          _SearchField(
            controller: onTemplates ? _templateSearch : _questionSearch,
            hint: onTemplates
                ? 'Search templates by name or key'
                : 'Search prompts, steps or keys',
            onChanged: (String value) => setState(() {
              if (onTemplates) {
                _templateQuery = value;
              } else {
                _questionQuery = value;
              }
            }),
          ),
          if (!onTemplates && _questions.isNotEmpty) ...<Widget>[
            const SizedBox(height: 10),
            SizedBox(
              height: 40,
              child: ListView(
                scrollDirection: Axis.horizontal,
                children: <Widget>[
                  _FilterChip(
                    label: 'All',
                    count: _questions.length,
                    color: _Palette.indigo,
                    selected: _typeFilter == 'all',
                    onSelected: () => setState(() => _typeFilter = 'all'),
                  ),
                  for (final MapEntry<String, _TypeMeta> entry
                      in _typeMeta.entries)
                    if (countByType(entry.key) > 0)
                      Padding(
                        padding: const EdgeInsets.only(left: 8),
                        child: _FilterChip(
                          label: entry.value.label,
                          count: countByType(entry.key),
                          color: entry.value.color,
                          selected: _typeFilter == entry.key,
                          onSelected: () =>
                              setState(() => _typeFilter = entry.key),
                        ),
                      ),
                ],
              ),
            ),
          ],
        ],
      ),
    );
  }

  Widget _templatesTab() {
    if (_loading && _templates.isEmpty && _questions.isEmpty) {
      return const _SkeletonList();
    }
    if (_error != null && _templates.isEmpty && _questions.isEmpty) {
      return _EmptyState(
        icon: Icons.cloud_off_rounded,
        gradient: _Palette.sunset,
        title: 'Could not load templates',
        message: _error!,
        actionLabel: 'Try again',
        onAction: () => _load(),
      );
    }
    final List<Map<String, dynamic>> visible = _visibleTemplates;
    if (visible.isEmpty) {
      return _EmptyState(
        icon: _templates.isEmpty
            ? Icons.folder_special_rounded
            : Icons.search_off_rounded,
        gradient: _Palette.berry,
        title: _templates.isEmpty ? 'No templates yet' : 'No matches',
        message: _templates.isEmpty
            ? 'Create a template, then build versioned questionnaires inside '
                  'it.'
            : 'Try a different template name or key.',
        actionLabel: _templates.isEmpty ? 'Create template' : 'Clear search',
        onAction: _templates.isEmpty
            ? _newTemplate
            : () => setState(() {
                _templateQuery = '';
                _templateSearch.clear();
              }),
      );
    }
    return ListView.separated(
      padding: const EdgeInsets.fromLTRB(16, 4, 16, 120),
      itemCount: visible.length,
      separatorBuilder: (BuildContext context, int index) =>
          const SizedBox(height: 14),
      itemBuilder: (BuildContext context, int index) {
        final Map<String, dynamic> template = visible[index];
        return _TemplateCard(
          template: template,
          onPublish: (Map<String, dynamic> version) =>
              _publish(template, version),
          onBuildVersion: () => _newVersion(template),
        );
      },
    );
  }

  Widget _questionsTab() {
    if (_loading && _templates.isEmpty && _questions.isEmpty) {
      return const _SkeletonList();
    }
    if (_error != null && _templates.isEmpty && _questions.isEmpty) {
      return _EmptyState(
        icon: Icons.cloud_off_rounded,
        gradient: _Palette.sunset,
        title: 'Could not load questions',
        message: _error!,
        actionLabel: 'Try again',
        onAction: () => _load(),
      );
    }
    final List<Map<String, dynamic>> visible = _visibleQuestions;
    if (visible.isEmpty) {
      return _EmptyState(
        icon: _questions.isEmpty
            ? Icons.quiz_rounded
            : Icons.search_off_rounded,
        gradient: _Palette.mint,
        title: _questions.isEmpty
            ? 'The question bank is empty'
            : 'No questions match',
        message: _questions.isEmpty
            ? 'Add the questions reviewers will answer, then assemble them '
                  'into a template version.'
            : 'Try another keyword or pick a different answer type.',
        actionLabel: _questions.isEmpty ? 'Add question' : 'Clear filters',
        onAction: _questions.isEmpty
            ? () => _openQuestionEditor()
            : () => setState(() {
                _questionQuery = '';
                _typeFilter = 'all';
                _questionSearch.clear();
              }),
      );
    }
    return ListView.separated(
      padding: const EdgeInsets.fromLTRB(16, 4, 16, 120),
      itemCount: visible.length,
      separatorBuilder: (BuildContext context, int index) =>
          const SizedBox(height: 10),
      itemBuilder: (BuildContext context, int index) {
        final Map<String, dynamic> question = visible[index];
        final bool locked = _isQuestionLocked(question);
        return _QuestionCard(
          question: question,
          locked: locked,
          fallbackOrder: index + 1,
          onEdit: locked ? null : () => _openQuestionEditor(existing: question),
        );
      },
    );
  }
}

// ---------------------------------------------------------------------------
// Cards
// ---------------------------------------------------------------------------

class _TemplateCard extends StatelessWidget {
  const _TemplateCard({
    required this.template,
    required this.onPublish,
    required this.onBuildVersion,
  });

  final Map<String, dynamic> template;
  final ValueChanged<Map<String, dynamic>> onPublish;
  final VoidCallback onBuildVersion;

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);
    final ColorScheme scheme = theme.colorScheme;
    final String status = template['status']?.toString() ?? 'draft';
    final List<Map<String, dynamic>> versions =
        template['versions'] is List
        ? (template['versions'] as List).map(_map).toList()
        : <Map<String, dynamic>>[];
    final int published = versions
        .where((Map<String, dynamic> row) => row['status'] == 'published')
        .length;
    final int questionCount = versions.fold<int>(
      0,
      (int sum, Map<String, dynamic> row) =>
          sum + (row['questions'] is List ? (row['questions'] as List).length : 0),
    );
    final String description = template['description']?.toString().trim() ?? '';
    final String key = template['template_key']?.toString().trim() ?? '';

    return Container(
      clipBehavior: Clip.antiAlias,
      decoration: BoxDecoration(
        color: scheme.surface,
        borderRadius: BorderRadius.circular(24),
        border: Border.all(color: scheme.outlineVariant.withValues(alpha: .7)),
        boxShadow: <BoxShadow>[
          BoxShadow(
            color: scheme.shadow.withValues(alpha: .06),
            blurRadius: 18,
            offset: const Offset(0, 8),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: <Widget>[
          Container(
            height: 4,
            decoration: const BoxDecoration(gradient: _Palette.hero),
          ),
          Padding(
            padding: const EdgeInsets.fromLTRB(14, 14, 14, 10),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: <Widget>[
                    const _IconBadge(
                      gradient: _Palette.berry,
                      icon: Icons.folder_special_rounded,
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: <Widget>[
                          Text(
                            template['name']?.toString() ?? 'Template',
                            maxLines: 2,
                            overflow: TextOverflow.ellipsis,
                            style: theme.textTheme.titleMedium?.copyWith(
                              fontWeight: FontWeight.w800,
                              height: 1.2,
                            ),
                          ),
                          const SizedBox(height: 3),
                          Text(
                            key.isEmpty ? 'No template key' : key,
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: theme.textTheme.bodySmall?.copyWith(
                              color: scheme.onSurfaceVariant,
                              fontWeight: FontWeight.w600,
                            ),
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(width: 8),
                    _StatusPill(status: status, color: _statusColor(status)),
                  ],
                ),
                if (description.isNotEmpty) ...<Widget>[
                  const SizedBox(height: 10),
                  Text(
                    description,
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                    style: theme.textTheme.bodySmall?.copyWith(
                      color: scheme.onSurfaceVariant,
                      height: 1.4,
                    ),
                  ),
                ],
                const SizedBox(height: 12),
                _TileRow(
                  spacing: 10,
                  minItemWidth: 92,
                  children: <Widget>[
                    _MetricBox(label: 'Versions', value: '${versions.length}'),
                    _MetricBox(label: 'Published', value: '$published'),
                    _MetricBox(label: 'Questions', value: '$questionCount'),
                  ],
                ),
              ],
            ),
          ),
          Theme(
            data: theme.copyWith(dividerColor: Colors.transparent),
            child: ExpansionTile(
              tilePadding: const EdgeInsets.symmetric(horizontal: 14),
              childrenPadding: const EdgeInsets.fromLTRB(14, 0, 14, 14),
              shape: const Border(),
              collapsedShape: const Border(),
              iconColor: _Palette.violet,
              collapsedIconColor: scheme.onSurfaceVariant,
              initiallyExpanded: versions.isNotEmpty && versions.length <= 4,
              title: Text(
                'Versions',
                style: theme.textTheme.titleSmall?.copyWith(
                  fontWeight: FontWeight.w800,
                ),
              ),
              subtitle: Text(
                versions.isEmpty
                    ? 'No versions built yet'
                    : '$published of ${versions.length} published',
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
              ),
              children: <Widget>[
                if (versions.isEmpty)
                  Container(
                    margin: const EdgeInsets.only(bottom: 10),
                    padding: const EdgeInsets.all(12),
                    decoration: BoxDecoration(
                      color: _Palette.amber.withValues(alpha: .08),
                      borderRadius: BorderRadius.circular(14),
                      border: Border.all(
                        color: _Palette.amber.withValues(alpha: .25),
                      ),
                    ),
                    child: Row(
                      children: <Widget>[
                        const Icon(
                          Icons.lightbulb_outline_rounded,
                          size: 18,
                          color: _Palette.amber,
                        ),
                        const SizedBox(width: 10),
                        Expanded(
                          child: Text(
                            'Pick questions from the bank to build the first '
                            'draft version.',
                            style: theme.textTheme.labelSmall?.copyWith(
                              color: scheme.onSurfaceVariant,
                              height: 1.35,
                            ),
                          ),
                        ),
                      ],
                    ),
                  ),
                for (final Map<String, dynamic> version in versions)
                  _VersionRow(version: version, onPublish: onPublish),
                const SizedBox(height: 4),
                Align(
                  alignment: Alignment.centerRight,
                  child: FilledButton.tonalIcon(
                    style: FilledButton.styleFrom(
                      backgroundColor: _Palette.violet.withValues(alpha: .14),
                      foregroundColor: _Palette.violet,
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(14),
                      ),
                    ),
                    onPressed: onBuildVersion,
                    icon: const Icon(Icons.add_rounded, size: 18),
                    label: const Text('Build version'),
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _VersionRow extends StatelessWidget {
  const _VersionRow({required this.version, required this.onPublish});

  final Map<String, dynamic> version;
  final ValueChanged<Map<String, dynamic>> onPublish;

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);
    final bool published = version['status'] == 'published';
    final Color color = published ? _Palette.green : _Palette.amber;
    final int count = version['questions'] is List
        ? (version['questions'] as List).length
        : 0;

    return Container(
      margin: const EdgeInsets.only(bottom: 8),
      padding: const EdgeInsets.fromLTRB(12, 10, 12, 10),
      decoration: BoxDecoration(
        color: color.withValues(alpha: .07),
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: color.withValues(alpha: .25)),
      ),
      child: Row(
        children: <Widget>[
          Container(
            width: 34,
            height: 34,
            decoration: BoxDecoration(
              color: color.withValues(alpha: .16),
              shape: BoxShape.circle,
            ),
            child: Icon(
              published ? Icons.verified_rounded : Icons.edit_note_rounded,
              size: 18,
              color: color,
            ),
          ),
          const SizedBox(width: 10),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                Text(
                  'Version ${version['version'] ?? '—'}',
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: theme.textTheme.bodyMedium?.copyWith(
                    fontWeight: FontWeight.w800,
                  ),
                ),
                Text(
                  '$count question'
                  '${count == 1 ? '' : 's'} · '
                  '${published ? 'Locked' : 'Draft'}',
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: theme.textTheme.labelSmall?.copyWith(
                    color: theme.colorScheme.onSurfaceVariant,
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(width: 8),
          if (published)
            Tooltip(
              message: 'Published versions are immutable',
              child: Icon(Icons.lock_outline_rounded, size: 18, color: color),
            )
          else
            SizedBox(
              width: 88,
              child: FilledButton(
                style: FilledButton.styleFrom(
                  backgroundColor: _Palette.green,
                  padding: const EdgeInsets.symmetric(
                    horizontal: 8,
                    vertical: 8,
                  ),
                  visualDensity: VisualDensity.compact,
                  textStyle: const TextStyle(
                    fontWeight: FontWeight.w700,
                    fontSize: 12.5,
                  ),
                ),
                onPressed: () => onPublish(version),
                child: const Text('Publish'),
              ),
            ),
        ],
      ),
    );
  }
}

class _QuestionCard extends StatelessWidget {
  const _QuestionCard({
    required this.question,
    required this.locked,
    required this.fallbackOrder,
    this.onEdit,
  });

  final Map<String, dynamic> question;
  final bool locked;
  final int fallbackOrder;
  final VoidCallback? onEdit;

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);
    final ColorScheme scheme = theme.colorScheme;
    final String type = question['type']?.toString() ?? '';
    final _TypeMeta meta = _metaFor(type);
    final String step = question['step_name']?.toString() ?? '';
    final List<String> options = question['options'] is List
        ? (question['options'] as List)
              .map((dynamic value) => value.toString())
              .where((String value) => value.isNotEmpty)
              .toList()
        : <String>[];
    final String order =
        question['question_order']?.toString() ?? '$fallbackOrder';

    return Container(
      padding: const EdgeInsets.fromLTRB(12, 12, 6, 12),
      decoration: BoxDecoration(
        color: scheme.surface,
        borderRadius: BorderRadius.circular(20),
        border: Border.all(
          color: (locked ? _Palette.green : scheme.outlineVariant).withValues(
            alpha: locked ? .35 : .7,
          ),
        ),
        boxShadow: <BoxShadow>[
          BoxShadow(
            color: scheme.shadow.withValues(alpha: .05),
            blurRadius: 14,
            offset: const Offset(0, 6),
          ),
        ],
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Container(
            width: 44,
            height: 44,
            decoration: BoxDecoration(
              gradient: LinearGradient(
                begin: Alignment.topLeft,
                end: Alignment.bottomRight,
                colors: <Color>[
                  meta.color,
                  Color.lerp(meta.color, Colors.black, .28)!,
                ],
              ),
              borderRadius: BorderRadius.circular(14),
            ),
            child: Stack(
              alignment: Alignment.center,
              children: <Widget>[
                Icon(meta.icon, color: Colors.white, size: 21),
                Positioned(
                  right: 2,
                  bottom: 1,
                  child: Text(
                    order,
                    style: TextStyle(
                      color: Colors.white.withValues(alpha: .9),
                      fontSize: 9,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                Text(
                  question['prompt']?.toString() ?? 'Untitled question',
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: theme.textTheme.bodyLarge?.copyWith(
                    fontWeight: FontWeight.w700,
                    height: 1.3,
                  ),
                ),
                const SizedBox(height: 8),
                Wrap(
                  spacing: 6,
                  runSpacing: 6,
                  children: <Widget>[
                    _MetaChip(
                      icon: meta.icon,
                      color: meta.color,
                      label: meta.label,
                    ),
                    if (question['is_attention_check'] == true)
                      const _MetaChip(
                        icon: Icons.fact_check_rounded,
                        color: _Palette.rose,
                        label: 'Attention check',
                      ),
                    if (step.isNotEmpty)
                      _MetaChip(
                        icon: Icons.layers_rounded,
                        color: _Palette.sky,
                        label: step,
                      ),
                    if (options.isNotEmpty)
                      _MetaChip(
                        icon: Icons.format_list_bulleted_rounded,
                        color: _Palette.violet,
                        label: '${options.length} options',
                      ),
                    if (locked)
                      const _MetaChip(
                        icon: Icons.lock_rounded,
                        color: _Palette.green,
                        label: 'In a published version',
                      ),
                  ],
                ),
              ],
            ),
          ),
          IconButton(
            tooltip: locked ? 'Locked by a published version' : 'Edit question',
            onPressed: onEdit,
            icon: const Icon(Icons.edit_outlined),
            color: locked ? scheme.onSurfaceVariant : _Palette.violet,
            style: IconButton.styleFrom(
              backgroundColor: _Palette.violet.withValues(
                alpha: locked ? 0 : .1,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

// ---------------------------------------------------------------------------
// Dialog chrome
// ---------------------------------------------------------------------------

/// Rounded dialog with a gradient header, scrollable body and an action bar.
/// `canPop` blocks dismissal (back button, barrier, close icon) while the
/// dialog is busy, and `onBlockedPop` explains why.
class _DialogShell extends StatelessWidget {
  const _DialogShell({
    required this.title,
    required this.subtitle,
    required this.icon,
    required this.gradient,
    required this.body,
    required this.actions,
    this.canPop = true,
    this.onBlockedPop,
    this.scrollBody = true,
    this.maxWidth = 560,
  });

  final String title;
  final String subtitle;
  final IconData icon;
  final LinearGradient gradient;
  final Widget body;
  final List<Widget> actions;
  final bool canPop;
  final VoidCallback? onBlockedPop;
  final bool scrollBody;
  final double maxWidth;

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);
    return PopScope(
      canPop: canPop,
      onPopInvokedWithResult: (bool didPop, Object? result) {
        if (!didPop) onBlockedPop?.call();
      },
      child: Dialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(26)),
        insetPadding: const EdgeInsets.symmetric(horizontal: 18, vertical: 28),
        child: ConstrainedBox(
          constraints: BoxConstraints(maxWidth: maxWidth),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: <Widget>[
              Container(
                padding: const EdgeInsets.fromLTRB(20, 18, 12, 18),
                decoration: BoxDecoration(
                  gradient: gradient,
                  borderRadius: const BorderRadius.vertical(
                    top: Radius.circular(26),
                  ),
                ),
                child: Row(
                  children: <Widget>[
                    Container(
                      width: 40,
                      height: 40,
                      decoration: BoxDecoration(
                        color: Colors.white.withValues(alpha: .2),
                        borderRadius: BorderRadius.circular(13),
                        border: Border.all(
                          color: Colors.white.withValues(alpha: .3),
                        ),
                      ),
                      child: Icon(icon, color: Colors.white, size: 21),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: <Widget>[
                          Text(
                            title,
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: const TextStyle(
                              color: Colors.white,
                              fontSize: 17,
                              fontWeight: FontWeight.w800,
                            ),
                          ),
                          const SizedBox(height: 2),
                          Text(
                            subtitle,
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: TextStyle(
                              color: Colors.white.withValues(alpha: .85),
                              fontSize: 12,
                            ),
                          ),
                        ],
                      ),
                    ),
                    IconButton(
                      tooltip: 'Close',
                      onPressed: canPop
                          ? () => Navigator.of(context).pop(false)
                          : onBlockedPop,
                      icon: const Icon(Icons.close_rounded, color: Colors.white),
                      style: IconButton.styleFrom(
                        backgroundColor: Colors.white.withValues(alpha: .16),
                        shape: const CircleBorder(),
                      ),
                    ),
                  ],
                ),
              ),
              Flexible(
                child: scrollBody
                    ? SingleChildScrollView(
                        padding: const EdgeInsets.fromLTRB(20, 18, 20, 4),
                        child: body,
                      )
                    : body,
              ),
              Padding(
                padding: const EdgeInsets.fromLTRB(20, 10, 20, 16),
                child: Wrap(
                  alignment: WrapAlignment.end,
                  spacing: 8,
                  runSpacing: 8,
                  children: actions,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _ConfirmDialog extends StatelessWidget {
  const _ConfirmDialog({
    required this.title,
    required this.icon,
    required this.gradient,
    required this.message,
    required this.confirmLabel,
    required this.cancelLabel,
  });

  final String title;
  final IconData icon;
  final LinearGradient gradient;
  final String message;
  final String confirmLabel;
  final String cancelLabel;

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);
    return Dialog(
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(26)),
      child: ConstrainedBox(
        constraints: const BoxConstraints(maxWidth: 420),
        child: Padding(
          padding: const EdgeInsets.fromLTRB(22, 22, 22, 16),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: <Widget>[
              Row(
                children: <Widget>[
                  _IconBadge(gradient: gradient, icon: icon),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Text(
                      title,
                      style: theme.textTheme.titleMedium?.copyWith(
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 14),
              Text(
                message,
                style: theme.textTheme.bodyMedium?.copyWith(
                  color: theme.colorScheme.onSurfaceVariant,
                  height: 1.45,
                ),
              ),
              const SizedBox(height: 20),
              Wrap(
                alignment: WrapAlignment.end,
                spacing: 8,
                children: <Widget>[
                  TextButton(
                    onPressed: () => Navigator.of(context).pop(false),
                    child: Text(cancelLabel),
                  ),
                  _GradientButton(
                    label: confirmLabel,
                    gradient: gradient,
                    onPressed: () => Navigator.of(context).pop(true),
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }
}

// ---------------------------------------------------------------------------
// Create template dialog
// ---------------------------------------------------------------------------

class _TemplateDialog extends StatefulWidget {
  const _TemplateDialog({required this.api, required this.token});

  final AuthApi api;
  final String token;

  @override
  State<_TemplateDialog> createState() => _TemplateDialogState();
}

class _TemplateDialogState extends State<_TemplateDialog> {
  final GlobalKey<FormState> _formKey = GlobalKey<FormState>();
  final TextEditingController _name = TextEditingController();
  final TextEditingController _key = TextEditingController();
  final TextEditingController _description = TextEditingController();
  bool _saving = false;
  String? _error;

  @override
  void dispose() {
    _name.dispose();
    _key.dispose();
    _description.dispose();
    super.dispose();
  }

  void _blocked() {
    final ScaffoldMessengerState? messenger = ScaffoldMessenger.maybeOf(
      context,
    );
    if (messenger != null) {
      _showToast(messenger, 'Creating the template — please wait.');
    }
  }

  Future<void> _save() async {
    if (_saving) return;
    final FormState? form = _formKey.currentState;
    if (form == null || !form.validate()) return;
    final ScaffoldMessengerState messenger = ScaffoldMessenger.of(context);
    setState(() {
      _saving = true;
      _error = null;
    });
    try {
      await widget.api.post('admin/review-templates', widget.token, <String,
          dynamic>{
        'name': _name.text.trim(),
        'template_key': _key.text.trim().isEmpty ? null : _key.text.trim(),
        'description': _description.text.trim().isEmpty
            ? null
            : _description.text.trim(),
      });
      _showToast(messenger, 'Template created.', success: true);
      if (mounted) Navigator.of(context).pop(true);
      return;
    } on AuthException catch (error) {
      _showToast(messenger, error.message, error: true);
      if (!mounted) return;
      setState(() {
        _saving = false;
        _error = error.message;
      });
    } catch (error) {
      _showToast(messenger, 'Unexpected error: $error', error: true);
      if (!mounted) return;
      setState(() {
        _saving = false;
        _error = '$error';
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    return _DialogShell(
      title: 'New question template',
      subtitle: 'Group related questionnaire versions',
      icon: Icons.folder_special_rounded,
      gradient: _Palette.berry,
      canPop: !_saving,
      onBlockedPop: _blocked,
      body: Form(
        key: _formKey,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: <Widget>[
            _DialogTextField(
              controller: _name,
              label: 'Template name',
              icon: Icons.badge_rounded,
              validator: _required,
            ),
            _DialogTextField(
              controller: _key,
              label: 'Template key (optional)',
              hint: 'letters, numbers and dashes',
              icon: Icons.key_rounded,
              validator: (String? value) {
                final String text = (value ?? '').trim();
                if (text.isEmpty) return null;
                return RegExp(r'^[A-Za-z0-9_-]+$').hasMatch(text)
                    ? null
                    : 'Use letters, numbers, underscores and dashes only';
              },
            ),
            _DialogTextField(
              controller: _description,
              label: 'Description',
              icon: Icons.notes_rounded,
              lines: 3,
              validator: (String? value) => null,
            ),
            if (_error != null) ...<Widget>[
              const SizedBox(height: 4),
              _ErrorBanner(message: _error!),
            ],
          ],
        ),
      ),
      actions: <Widget>[
        TextButton(
          onPressed: _saving ? null : () => Navigator.of(context).pop(false),
          child: const Text('Cancel'),
        ),
        _GradientButton(
          label: _saving ? 'Creating…' : 'Create template',
          icon: _saving ? null : Icons.check_rounded,
          busy: _saving,
          gradient: _Palette.berry,
          onPressed: _saving ? null : _save,
        ),
      ],
    );
  }
}

// ---------------------------------------------------------------------------
// Build version dialog
// ---------------------------------------------------------------------------

class _VersionBuilderDialog extends StatefulWidget {
  const _VersionBuilderDialog({
    required this.api,
    required this.token,
    required this.template,
    required this.questions,
  });

  final AuthApi api;
  final String token;
  final Map<String, dynamic> template;
  final List<Map<String, dynamic>> questions;

  @override
  State<_VersionBuilderDialog> createState() => _VersionBuilderDialogState();
}

class _VersionBuilderDialogState extends State<_VersionBuilderDialog> {
  final TextEditingController _version = TextEditingController();
  final TextEditingController _search = TextEditingController();
  final Set<int> _selected = <int>{};
  String _filter = '';
  bool _saving = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    int latest = 0;
    final List<Map<String, dynamic>> versions =
        widget.template['versions'] is List
        ? (widget.template['versions'] as List).map(_map).toList()
        : <Map<String, dynamic>>[];
    for (final Map<String, dynamic> row in versions) {
      final int value = int.tryParse(row['version']?.toString() ?? '') ?? 0;
      if (value > latest) latest = value;
    }
    _version.text = '${latest + 1}';
  }

  @override
  void dispose() {
    _version.dispose();
    _search.dispose();
    super.dispose();
  }

  void _blocked() {
    final ScaffoldMessengerState? messenger = ScaffoldMessenger.maybeOf(
      context,
    );
    if (messenger != null) {
      _showToast(messenger, 'Saving the version — please wait.');
    }
  }

  List<Map<String, dynamic>> get _filtered {
    final String needle = _filter.trim().toLowerCase();
    if (needle.isEmpty) return widget.questions;
    return widget.questions
        .where((Map<String, dynamic> question) =>
            '${question['prompt']} ${question['step_name']} '
                    '${question['question_key']}'
                .toLowerCase()
                .contains(needle))
        .toList();
  }

  void _toggle(Map<String, dynamic> question) {
    final int? id = int.tryParse(question['id']?.toString() ?? '');
    if (id == null) return;
    setState(() {
      if (!_selected.remove(id)) _selected.add(id);
      _error = null;
    });
  }

  Future<void> _save() async {
    if (_saving) return;
    final int? number = int.tryParse(_version.text.trim());
    if (number == null || number < 1 || _selected.isEmpty) {
      setState(
        () =>
            _error =
                'Enter a version number and select at least one question.',
      );
      return;
    }
    final ScaffoldMessengerState messenger = ScaffoldMessenger.of(context);
    setState(() {
      _saving = true;
      _error = null;
    });
    final List<Map<String, dynamic>> ordered = widget.questions
        .where((Map<String, dynamic> question) =>
            _selected.contains(int.tryParse(question['id'].toString())))
        .toList();
    try {
      await widget.api.post(
        'admin/review-templates/${widget.template['id']}/versions',
        widget.token,
        <String, dynamic>{
          'version': number,
          'questions': <Map<String, dynamic>>[
            for (int index = 0; index < ordered.length; index++)
              <String, dynamic>{
                'question_id': ordered[index]['id'],
                'step_order':
                    int.tryParse(
                      ordered[index]['step_order']?.toString() ?? '',
                    ) ??
                    1,
                'step_name':
                    ordered[index]['step_name']?.toString() ?? 'Review',
                'question_order': index + 1,
              },
          ],
        },
      );
      _showToast(messenger, 'Draft version $number created.', success: true);
      if (mounted) Navigator.of(context).pop(true);
      return;
    } on AuthException catch (error) {
      _showToast(messenger, error.message, error: true);
      if (!mounted) return;
      setState(() {
        _saving = false;
        _error = error.message;
      });
    } catch (error) {
      _showToast(messenger, 'Unexpected error: $error', error: true);
      if (!mounted) return;
      setState(() {
        _saving = false;
        _error = '$error';
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);
    final List<Map<String, dynamic>> visible = _filtered;

    return _DialogShell(
      title: 'Build ${widget.template['name'] ?? 'template'} version',
      subtitle: 'Pick the questions this version asks',
      icon: Icons.build_rounded,
      gradient: _Palette.ocean,
      canPop: !_saving,
      onBlockedPop: _blocked,
      body: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: <Widget>[
          Row(
            children: <Widget>[
              Expanded(
                child: _DialogTextField(
                  controller: _version,
                  label: 'Version number',
                  icon: Icons.tag_rounded,
                  keyboard: TextInputType.number,
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Container(
                  height: 52,
                  alignment: Alignment.center,
                  padding: const EdgeInsets.symmetric(horizontal: 12),
                  decoration: BoxDecoration(
                    gradient: _Palette.mint,
                    borderRadius: BorderRadius.circular(14),
                  ),
                  child: Text(
                    '${_selected.length} selected',
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                      color: Colors.white,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ),
              ),
            ],
          ),
          _SearchField(
            controller: _search,
            hint: 'Filter questions by prompt or step',
            onChanged: (String value) => setState(() => _filter = value),
          ),
          const SizedBox(height: 8),
          Row(
            children: <Widget>[
              Expanded(
                child: Text(
                  visible.isEmpty
                      ? 'No questions match that filter'
                      : '${visible.length} question'
                            '${visible.length == 1 ? '' : 's'} available',
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: theme.textTheme.labelSmall?.copyWith(
                    color: theme.colorScheme.onSurfaceVariant,
                  ),
                ),
              ),
              TextButton(
                onPressed: _saving
                    ? null
                    : () => setState(() {
                        for (final Map<String, dynamic> question in visible) {
                          final int? id = int.tryParse(
                            question['id']?.toString() ?? '',
                          );
                          if (id != null) _selected.add(id);
                        }
                        _error = null;
                      }),
                child: const Text('Select all'),
              ),
              TextButton(
                onPressed: _saving || _selected.isEmpty
                    ? null
                    : () => setState(() {
                        _selected.clear();
                        _error = null;
                      }),
                child: const Text('Clear'),
              ),
            ],
          ),
          const SizedBox(height: 4),
          if (visible.isEmpty)
            Container(
              padding: const EdgeInsets.all(14),
              decoration: BoxDecoration(
                color: _Palette.amber.withValues(alpha: .08),
                borderRadius: BorderRadius.circular(14),
              ),
              child: Text(
                'Nothing to pick yet — adjust the filter or add questions in '
                'the question bank.',
                style: theme.textTheme.labelSmall?.copyWith(
                  color: theme.colorScheme.onSurfaceVariant,
                ),
              ),
            )
          else
            for (final Map<String, dynamic> question in visible)
              _buildQuestionRow(theme, question),
          if (_error != null) ...<Widget>[
            const SizedBox(height: 12),
            _ErrorBanner(message: _error!),
          ],
        ],
      ),
      actions: <Widget>[
        TextButton(
          onPressed: _saving ? null : () => Navigator.of(context).pop(false),
          child: const Text('Cancel'),
        ),
        _GradientButton(
          label: _saving ? 'Creating version…' : 'Create draft version',
          icon: _saving ? null : Icons.playlist_add_check_rounded,
          busy: _saving,
          gradient: _Palette.ocean,
          onPressed: _saving ? null : _save,
        ),
      ],
    );
  }

  Widget _buildQuestionRow(
    ThemeData theme,
    Map<String, dynamic> question,
  ) {
    final int? id = int.tryParse(question['id']?.toString() ?? '');
    final bool checked = id != null && _selected.contains(id);
    final _TypeMeta meta = _metaFor(question['type']?.toString());
    return InkWell(
      borderRadius: BorderRadius.circular(14),
      onTap: _saving ? null : () => _toggle(question),
      child: Container(
        margin: const EdgeInsets.only(bottom: 6),
        padding: const EdgeInsets.fromLTRB(8, 8, 12, 8),
        decoration: BoxDecoration(
          color: checked
              ? _Palette.indigo.withValues(alpha: .09)
              : Colors.transparent,
          borderRadius: BorderRadius.circular(14),
          border: Border.all(
            color: checked
                ? _Palette.indigo.withValues(alpha: .35)
                : theme.colorScheme.outlineVariant.withValues(alpha: .6),
          ),
        ),
        child: Row(
          children: <Widget>[
            Checkbox(
              value: checked,
              onChanged: _saving ? null : (bool? value) => _toggle(question),
            ),
            const SizedBox(width: 4),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: <Widget>[
                  Text(
                    question['prompt']?.toString() ?? 'Question',
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                    style: theme.textTheme.bodyMedium?.copyWith(
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    '${question['step_name'] ?? 'Unassigned step'} · '
                    '${meta.label}',
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: theme.textTheme.labelSmall?.copyWith(
                      color: meta.color,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

// ---------------------------------------------------------------------------
// Question editor dialog
// ---------------------------------------------------------------------------

class _QuestionEditor extends StatefulWidget {
  const _QuestionEditor({
    required this.api,
    required this.token,
    this.existing,
  });

  final AuthApi api;
  final String token;
  final Map<String, dynamic>? existing;

  @override
  State<_QuestionEditor> createState() => _QuestionEditorState();
}

class _QuestionEditorState extends State<_QuestionEditor> {
  static const List<String> _fieldKeys = <String>[
    'question_key',
    'step_name',
    'step_order',
    'question_order',
    'prompt',
    'hint',
  ];

  final GlobalKey<FormState> _formKey = GlobalKey<FormState>();
  late final Map<String, TextEditingController> _fields;
  late List<TextEditingController> _options;

  /// Answer-option controllers removed from the form are parked here until the
  /// state is disposed. Disposing one while its `TextFormField` is still in
  /// the tree throws "used after being disposed" when that element unmounts.
  final List<TextEditingController> _retiredOptions =
      <TextEditingController>[];

  late String _type;
  late TextEditingController _attentionAnswerController;
  bool _isAttentionCheck = false;
  bool _saving = false;
  String? _error;

  bool get _isChoice => _type == 'single_choice' || _type == 'multiple_choice';

  @override
  void initState() {
    super.initState();
    final Map<String, dynamic> row =
        widget.existing ?? const <String, dynamic>{};
    _type = row['type']?.toString() ?? 'single_choice';
    _isAttentionCheck = row['is_attention_check'] == true;
    final dynamic attentionAnswer = row['attention_check_answer'];
    _attentionAnswerController = TextEditingController(
      text: attentionAnswer is List
          ? attentionAnswer.join(', ')
          : attentionAnswer is Map
          ? (attentionAnswer['phrase']?.toString() ?? '')
          : (attentionAnswer?.toString() ?? ''),
    );
    _fields = <String, TextEditingController>{
      for (final String key in _fieldKeys) key: _controllerFor(key, row),
    };
    final dynamic rawOptions = row['options'];
    _options = rawOptions is List && rawOptions.isNotEmpty
        ? rawOptions
              .map(
                (dynamic value) =>
                    TextEditingController(text: value.toString()),
              )
              .toList()
        : <TextEditingController>[TextEditingController()];
  }

  TextEditingController _controllerFor(
    String key,
    Map<String, dynamic> row,
  ) {
    final String? existing = row[key]?.toString();
    if (existing != null) return TextEditingController(text: existing);
    String fallback = '';
    if (key == 'step_name') {
      fallback = 'App experience';
    } else if (key == 'step_order' || key == 'question_order') {
      fallback = '1';
    }
    return TextEditingController(text: fallback);
  }

  @override
  void dispose() {
    for (final TextEditingController controller in _fields.values) {
      controller.dispose();
    }
    _attentionAnswerController.dispose();
    for (final TextEditingController controller in _options) {
      controller.dispose();
    }
    for (final TextEditingController controller in _retiredOptions) {
      controller.dispose();
    }
    super.dispose();
  }

  void _blocked() {
    final ScaffoldMessengerState? messenger = ScaffoldMessenger.maybeOf(
      context,
    );
    if (messenger != null) {
      _showToast(messenger, 'Saving the question — please wait.');
    }
  }

  void _removeOption(int index) {
    setState(() {
      _retiredOptions.add(_options.removeAt(index));
      _error = null;
    });
  }

  void _addOption() {
    setState(() {
      _options.add(TextEditingController());
      _error = null;
    });
  }

  Future<void> _save() async {
    if (_saving) return;
    final FormState? form = _formKey.currentState;
    if (form == null || !form.validate()) return;
    final List<String> options = _options
        .map((TextEditingController controller) => controller.text.trim())
        .where((String value) => value.isNotEmpty)
        .toList();
    if (_isChoice && options.length < 2) {
      setState(() => _error = 'Add at least two answer options.');
      return;
    }
    final dynamic attentionAnswer = _buildAttentionAnswer(options);
    if (_isAttentionCheck && attentionAnswer == null) {
      setState(() => _error = 'Set the required answer for this attention check.');
      return;
    }
    final ScaffoldMessengerState messenger = ScaffoldMessenger.of(context);
    setState(() {
      _saving = true;
      _error = null;
    });
    try {
      final Map<String, dynamic> payload = <String, dynamic>{
        for (final String key in <String>['step_name', 'prompt', 'hint'])
          key: _fields[key]!.text.trim().isEmpty
              ? null
              : _fields[key]!.text.trim(),
        'step_order': int.tryParse(_fields['step_order']!.text.trim()) ?? 1,
        'question_order':
            int.tryParse(_fields['question_order']!.text.trim()) ?? 1,
        'type': _type,
        'options': _isChoice ? options : null,
        'is_attention_check': _isAttentionCheck,
        'attention_check_answer': _isAttentionCheck ? attentionAnswer : null,
        'settings': _type == 'rating'
            ? <String, dynamic>{'scale': <String, int>{'min': 1, 'max': 5}}
            : <String, dynamic>{},
        if (widget.existing == null)
          'question_key': _fields['question_key']!.text.trim(),
      };
      if (widget.existing == null) {
        await widget.api.post('admin/review-questions', widget.token, payload);
      } else {
        await widget.api.patch(
          'admin/review-questions/${widget.existing!['id']}',
          widget.token,
          payload..remove('question_key'),
        );
      }
      _showToast(
        messenger,
        widget.existing == null ? 'Question created.' : 'Question updated.',
        success: true,
      );
      if (mounted) Navigator.of(context).pop(true);
      return;
    } on AuthException catch (error) {
      _showToast(messenger, error.message, error: true);
      if (!mounted) return;
      setState(() {
        _saving = false;
        _error = error.message;
      });
    } catch (error) {
      _showToast(messenger, 'Unexpected error: $error', error: true);
      if (!mounted) return;
      setState(() {
        _saving = false;
        _error = '$error';
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);
    final bool creating = widget.existing == null;

    return _DialogShell(
      title: creating ? 'New question' : 'Edit question',
      subtitle: 'Prompt, ordering and answer type',
      icon: Icons.quiz_rounded,
      gradient: _Palette.ocean,
      canPop: !_saving,
      onBlockedPop: _blocked,
      body: Form(
        key: _formKey,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: <Widget>[
            if (creating)
              _DialogTextField(
                controller: _fields['question_key'],
                label: 'Question key',
                hint: 'letters, numbers, dashes',
                icon: Icons.key_rounded,
                validator: _required,
              ),
            _DialogTextField(
              controller: _fields['step_name'],
              label: 'Question step / section',
              icon: Icons.layers_rounded,
              validator: _required,
            ),
            Row(
              children: <Widget>[
                Expanded(
                  child: _DialogTextField(
                    controller: _fields['step_order'],
                    label: 'Step number',
                    icon: Icons.format_list_numbered_rounded,
                    keyboard: TextInputType.number,
                    validator: _required,
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: _DialogTextField(
                    controller: _fields['question_order'],
                    label: 'Order in step',
                    icon: Icons.sort_rounded,
                    keyboard: TextInputType.number,
                    validator: _required,
                  ),
                ),
              ],
            ),
            _label(theme, 'Answer type'),
            Wrap(
              children: <Widget>[
                for (final String key in _typeMeta.keys) _typeChip(key),
              ],
            ),
            const SizedBox(height: 14),
            _DialogTextField(
              controller: _fields['prompt'],
              label: 'Question prompt',
              icon: Icons.question_answer_rounded,
              lines: 3,
              validator: _required,
            ),
            _DialogTextField(
              controller: _fields['hint'],
              label: 'Hint or helper text (optional)',
              icon: Icons.lightbulb_outline_rounded,
              lines: 2,
              validator: (String? value) => null,
            ),
            SwitchListTile.adaptive(
              contentPadding: EdgeInsets.zero,
              value: _isAttentionCheck,
              onChanged: _saving
                  ? null
                  : (bool value) => setState(() {
                      _isAttentionCheck = value;
                      _error = null;
                    }),
              title: const Text('Attention check'),
              subtitle: const Text(
                'Require a specific answer stated in the question prompt.',
              ),
            ),
            if (_isAttentionCheck) _buildAttentionAnswerField(),
            if (_isChoice) ...<Widget>[
              _label(theme, 'Answer options'),
              for (int index = 0; index < _options.length; index++)
                Padding(
                  padding: const EdgeInsets.only(bottom: 10),
                  child: Row(
                    children: <Widget>[
                      Container(
                        width: 26,
                        height: 26,
                        alignment: Alignment.center,
                        decoration: BoxDecoration(
                          color: _Palette.indigo.withValues(alpha: .12),
                          shape: BoxShape.circle,
                        ),
                        child: Text(
                          '${index + 1}',
                          style: const TextStyle(
                            color: _Palette.indigo,
                            fontSize: 11.5,
                            fontWeight: FontWeight.w800,
                          ),
                        ),
                      ),
                      const SizedBox(width: 10),
                      Expanded(
                        child: TextField(
                          controller: _options[index],
                          decoration: InputDecoration(
                            hintText: 'Option ${index + 1}',
                            isDense: true,
                            filled: true,
                            fillColor: theme.colorScheme.onSurface.withValues(
                              alpha: .04,
                            ),
                            contentPadding: const EdgeInsets.symmetric(
                              horizontal: 14,
                              vertical: 14,
                            ),
                            border: OutlineInputBorder(
                              borderRadius: BorderRadius.circular(14),
                              borderSide: BorderSide.none,
                            ),
                          ),
                        ),
                      ),
                      IconButton(
                        tooltip: _options.length == 1
                            ? 'At least one option is required'
                            : 'Remove option',
                        onPressed: _options.length == 1
                            ? null
                            : () => _removeOption(index),
                        icon: const Icon(Icons.remove_circle_outline_rounded),
                        color: _Palette.rose,
                      ),
                    ],
                  ),
                ),
              Align(
                alignment: Alignment.centerLeft,
                child: TextButton.icon(
                  onPressed: _addOption,
                  icon: const Icon(Icons.add_rounded, size: 18),
                  label: const Text('Add option'),
                  style: TextButton.styleFrom(foregroundColor: _Palette.indigo),
                ),
              ),
              const SizedBox(height: 6),
            ],
            _buildPreview(theme),
            if (_error != null) ...<Widget>[
              const SizedBox(height: 14),
              _ErrorBanner(message: _error!),
            ],
          ],
        ),
      ),
      actions: <Widget>[
        TextButton(
          onPressed: _saving ? null : () => Navigator.of(context).pop(false),
          child: const Text('Cancel'),
        ),
        _GradientButton(
          label: _saving ? 'Saving…' : 'Save question',
          icon: _saving ? null : Icons.save_rounded,
          busy: _saving,
          gradient: _Palette.ocean,
          onPressed: _saving ? null : _save,
        ),
      ],
    );
  }

  dynamic _buildAttentionAnswer(List<String> options) {
    final String value = _attentionAnswerController.text.trim();
    if (value.isEmpty) return null;
    return switch (_type) {
      'single_choice' => options.contains(value) ? value : null,
      'multiple_choice' => _multipleChoiceAttentionAnswer(value, options),
      'rating' => num.tryParse(value),
      'confirmation' => value.toLowerCase() == 'true'
          ? true
          : value.toLowerCase() == 'false'
          ? false
          : null,
      'long_text' => <String, dynamic>{
          'mode': 'contains',
          'phrase': value,
          'case_sensitive': false,
        },
      _ => value,
    };
  }

  List<String>? _multipleChoiceAttentionAnswer(
    String value,
    List<String> options,
  ) {
    final List<String> answers = value
        .split(',')
        .map((String item) => item.trim())
        .where((String item) => item.isNotEmpty)
        .toList();
    return answers.isNotEmpty && answers.every(options.contains)
        ? answers
        : null;
  }

  Widget _buildAttentionAnswerField() {
    if (_type == 'single_choice') {
      return AnimatedBuilder(
        animation: Listenable.merge(_options),
        builder: (BuildContext context, Widget? child) {
          final List<String> currentOptions = _options
              .map((TextEditingController controller) => controller.text.trim())
              .where((String value) => value.isNotEmpty)
              .toList();
          final String? selected = currentOptions.contains(
            _attentionAnswerController.text,
          )
              ? _attentionAnswerController.text
              : null;
          return DropdownButtonFormField<String>(
            value: selected,
            decoration: const InputDecoration(
              labelText: 'Required answer',
              helperText: 'Choose the response required by the prompt.',
            ),
            items: <DropdownMenuItem<String>>[
              for (final String option in currentOptions)
                DropdownMenuItem<String>(value: option, child: Text(option)),
            ],
            onChanged: _saving
                ? null
                : (String? value) => setState(() {
                    _attentionAnswerController.text = value ?? '';
                    _error = null;
                  }),
          );
        },
      );
    }
    if (_type == 'multiple_choice') {
      return _DialogTextField(
        controller: _attentionAnswerController,
        label: 'Required answer(s)',
        hint: 'Enter exact option labels separated by commas',
        icon: Icons.checklist_rounded,
        lines: 2,
        validator: (String? value) => null,
      );
    }
    if (_type == 'confirmation') {
      final String? selected = <String>['true', 'false'].contains(
        _attentionAnswerController.text.toLowerCase(),
      )
          ? _attentionAnswerController.text.toLowerCase()
          : null;
      return DropdownButtonFormField<String>(
        value: selected,
        decoration: const InputDecoration(labelText: 'Required response'),
        items: const <DropdownMenuItem<String>>[
          DropdownMenuItem<String>(value: 'true', child: Text('Yes / confirmed')),
          DropdownMenuItem<String>(value: 'false', child: Text('No / not confirmed')),
        ],
        onChanged: _saving
            ? null
            : (String? value) => setState(() {
                _attentionAnswerController.text = value ?? '';
                _error = null;
              }),
      );
    }
    return _DialogTextField(
      controller: _attentionAnswerController,
      label: _type == 'rating' ? 'Required rating' : 'Required phrase',
      hint: _type == 'rating'
          ? 'Enter a number within the rating scale'
          : 'The response must include this phrase.',
      icon: Icons.rule_rounded,
      keyboard: _type == 'rating' ? TextInputType.number : TextInputType.text,
      validator: (String? value) => null,
    );
  }

  Widget _label(ThemeData theme, String text) => Padding(
    padding: const EdgeInsets.only(bottom: 8),
    child: Text(
      text,
      style: theme.textTheme.labelLarge?.copyWith(
        fontWeight: FontWeight.w800,
        color: theme.colorScheme.onSurfaceVariant,
      ),
    ),
  );

  Widget _typeChip(String key) {
    final _TypeMeta meta = _metaFor(key);
    final bool selected = _type == key;
    return Padding(
      padding: const EdgeInsets.only(right: 8, bottom: 8),
      child: Material(
        color: selected ? meta.color : meta.color.withValues(alpha: .09),
        borderRadius: BorderRadius.circular(999),
        child: InkWell(
          borderRadius: BorderRadius.circular(999),
          onTap: _saving
              ? null
              : () => setState(() {
                  _type = key;
                  _error = null;
                }),
          child: Padding(
            padding: const EdgeInsets.symmetric(horizontal: 13, vertical: 9),
            child: Row(
              mainAxisSize: MainAxisSize.min,
              children: <Widget>[
                Icon(
                  meta.icon,
                  size: 15,
                  color: selected ? Colors.white : meta.color,
                ),
                const SizedBox(width: 6),
                Text(
                  meta.label,
                  style: TextStyle(
                    color: selected ? Colors.white : meta.color,
                    fontWeight: FontWeight.w700,
                    fontSize: 12.5,
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }

  /// Live "how the reviewer will see it" preview. Rebuilds whenever any field
  /// or option controller changes.
  Widget _buildPreview(ThemeData theme) {
    final _TypeMeta meta = _metaFor(_type);
    return AnimatedBuilder(
      animation: Listenable.merge(<Listenable>[
        ..._fields.values,
        ..._options,
      ]),
      builder: (BuildContext context, Widget? child) {
        final String prompt = _fields['prompt']!.text.trim();
        final String hint = _fields['hint']!.text.trim();
        final List<String> options = _options
            .map((TextEditingController controller) => controller.text.trim())
            .where((String value) => value.isNotEmpty)
            .toList();
        return Container(
          padding: const EdgeInsets.all(14),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(18),
            gradient: LinearGradient(
              begin: Alignment.topLeft,
              end: Alignment.bottomRight,
              colors: <Color>[
                meta.color.withValues(alpha: .12),
                _Palette.violet.withValues(alpha: .08),
              ],
            ),
            border: Border.all(color: meta.color.withValues(alpha: .3)),
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: <Widget>[
              Row(
                children: <Widget>[
                  Icon(meta.icon, size: 16, color: meta.color),
                  const SizedBox(width: 8),
                  Expanded(
                    child: Text(
                      'Reviewer preview · ${meta.label}',
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: TextStyle(
                        color: meta.color,
                        fontWeight: FontWeight.w800,
                        fontSize: 12,
                      ),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 10),
              Text(
                prompt.isEmpty ? 'Your question prompt appears here' : prompt,
                style: theme.textTheme.bodyMedium?.copyWith(
                  fontWeight: FontWeight.w700,
                  color: prompt.isEmpty
                      ? theme.colorScheme.onSurfaceVariant
                      : null,
                  height: 1.35,
                ),
              ),
              if (hint.isNotEmpty) ...<Widget>[
                const SizedBox(height: 4),
                Text(
                  hint,
                  style: theme.textTheme.labelSmall?.copyWith(
                    color: theme.colorScheme.onSurfaceVariant,
                    height: 1.35,
                  ),
                ),
              ],
              const SizedBox(height: 12),
              _previewAnswer(theme, options),
            ],
          ),
        );
      },
    );
  }

  Widget _previewAnswer(ThemeData theme, List<String> options) {
    switch (_type) {
      case 'single_choice':
      case 'multiple_choice':
        final bool single = _type == 'single_choice';
        if (options.isEmpty) {
          return Text(
            'Add options to preview the answer list.',
            style: theme.textTheme.labelSmall?.copyWith(
              color: theme.colorScheme.onSurfaceVariant,
            ),
          );
        }
        return Wrap(
          spacing: 8,
          runSpacing: 8,
          children: <Widget>[
            for (final String option in options)
              Container(
                padding: const EdgeInsets.symmetric(
                  horizontal: 11,
                  vertical: 8,
                ),
                decoration: BoxDecoration(
                  color: theme.colorScheme.surface,
                  borderRadius: BorderRadius.circular(999),
                  border: Border.all(
                    color: theme.colorScheme.outlineVariant,
                  ),
                ),
                child: Row(
                  mainAxisSize: MainAxisSize.min,
                  children: <Widget>[
                    Icon(
                      single
                          ? Icons.radio_button_unchecked_rounded
                          : Icons.check_box_outline_blank_rounded,
                      size: 15,
                      color: theme.colorScheme.onSurfaceVariant,
                    ),
                    const SizedBox(width: 7),
                    ConstrainedBox(
                      constraints: const BoxConstraints(maxWidth: 180),
                      child: Text(
                        option,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: theme.textTheme.labelMedium,
                      ),
                    ),
                  ],
                ),
              ),
          ],
        );
      case 'rating':
        return Row(
          mainAxisSize: MainAxisSize.min,
          children: <Widget>[
            for (int star = 1; star <= 5; star++)
              Icon(
                star <= 4 ? Icons.star_rounded : Icons.star_outline_rounded,
                size: 24,
                color: _Palette.amber,
              ),
          ],
        );
      case 'confirmation':
        return Row(
          mainAxisSize: MainAxisSize.min,
          children: <Widget>[
            const Icon(
              Icons.check_box_outline_blank_rounded,
              size: 18,
              color: _Palette.green,
            ),
            const SizedBox(width: 8),
            Text(
              'I confirm this is my honest review',
              style: theme.textTheme.labelMedium,
            ),
          ],
        );
      default:
        return Container(
          height: 64,
          width: double.infinity,
          decoration: BoxDecoration(
            color: theme.colorScheme.surface,
            borderRadius: BorderRadius.circular(12),
            border: Border.all(color: theme.colorScheme.outlineVariant),
          ),
          alignment: Alignment.topLeft,
          padding: const EdgeInsets.all(10),
          child: Text(
            'Long answer…',
            style: theme.textTheme.labelSmall?.copyWith(
              color: theme.colorScheme.onSurfaceVariant,
            ),
          ),
        );
    }
  }
}

// ---------------------------------------------------------------------------
// Shared primitives
// ---------------------------------------------------------------------------

class _DialogTextField extends StatelessWidget {
  const _DialogTextField({
    required this.controller,
    required this.label,
    this.hint,
    this.lines = 1,
    this.keyboard,
    this.icon,
    this.validator,
    this.maxLength,
  });

  final TextEditingController? controller;
  final String label;
  final String? hint;
  final int lines;
  final TextInputType? keyboard;
  final IconData? icon;
  final String? Function(String?)? validator;
  final int? maxLength;

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);
    final ColorScheme scheme = theme.colorScheme;
    return Padding(
      padding: const EdgeInsets.only(bottom: 14),
      child: TextFormField(
        controller: controller,
        maxLines: lines,
        minLines: lines > 1 ? 2 : null,
        maxLength: maxLength,
        keyboardType: keyboard,
        validator: validator,
        decoration: InputDecoration(
          labelText: label,
          hintText: hint,
          prefixIcon: icon == null ? null : Icon(icon, size: 20),
          alignLabelWithHint: lines > 1,
          filled: true,
          fillColor: scheme.onSurface.withValues(alpha: .04),
          contentPadding: const EdgeInsets.symmetric(
            horizontal: 14,
            vertical: 15,
          ),
          border: OutlineInputBorder(
            borderRadius: BorderRadius.circular(14),
            borderSide: BorderSide.none,
          ),
          enabledBorder: OutlineInputBorder(
            borderRadius: BorderRadius.circular(14),
            borderSide: BorderSide.none,
          ),
          focusedBorder: OutlineInputBorder(
            borderRadius: BorderRadius.circular(14),
            borderSide: const BorderSide(color: _Palette.indigo, width: 1.6),
          ),
          errorBorder: OutlineInputBorder(
            borderRadius: BorderRadius.circular(14),
            borderSide: const BorderSide(color: _Palette.rose, width: 1.4),
          ),
          focusedErrorBorder: OutlineInputBorder(
            borderRadius: BorderRadius.circular(14),
            borderSide: const BorderSide(color: _Palette.rose, width: 1.8),
          ),
        ),
      ),
    );
  }
}

class _TileRow extends StatelessWidget {
  const _TileRow({
    required this.children,
    required this.minItemWidth,
    this.spacing = 12,
  });

  final List<Widget> children;
  final double minItemWidth;
  final double spacing;

  @override
  Widget build(BuildContext context) {
    if (children.isEmpty) return const SizedBox.shrink();
    return LayoutBuilder(
      builder: (BuildContext context, BoxConstraints constraints) {
        final double span = constraints.maxWidth.isFinite
            ? constraints.maxWidth
            : minItemWidth;
        int columns = ((span + spacing) / (minItemWidth + spacing)).floor();
        if (columns < 1) columns = 1;
        if (columns > children.length) columns = children.length;
        final double width = (span - spacing * (columns - 1)) / columns;
        return Wrap(
          spacing: spacing,
          runSpacing: spacing,
          children: <Widget>[
            for (final Widget child in children)
              SizedBox(width: width, child: child),
          ],
        );
      },
    );
  }
}

class _IconBadge extends StatelessWidget {
  const _IconBadge({
    required this.gradient,
    required this.icon,
    this.size = 46,
  });

  final LinearGradient gradient;
  final IconData icon;
  final double size;

  @override
  Widget build(BuildContext context) => Container(
    width: size,
    height: size,
    decoration: BoxDecoration(
      gradient: gradient,
      borderRadius: BorderRadius.circular(size * .32),
    ),
    alignment: Alignment.center,
    child: Icon(icon, color: Colors.white, size: size * .5),
  );
}

class _StatusPill extends StatelessWidget {
  const _StatusPill({required this.status, required this.color});

  final String status;
  final Color color;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 5),
    decoration: BoxDecoration(
      color: color.withValues(alpha: .14),
      borderRadius: BorderRadius.circular(999),
      border: Border.all(color: color.withValues(alpha: .3)),
    ),
    child: Row(
      mainAxisSize: MainAxisSize.min,
      children: <Widget>[
        Icon(_statusIcon(status), size: 13, color: color),
        const SizedBox(width: 4),
        Flexible(
          child: Text(
            status.toUpperCase(),
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: TextStyle(
              color: color,
              fontSize: 10,
              fontWeight: FontWeight.w800,
              letterSpacing: .6,
            ),
          ),
        ),
      ],
    ),
  );
}

class _MetaChip extends StatelessWidget {
  const _MetaChip({
    required this.icon,
    required this.color,
    required this.label,
  });

  final IconData icon;
  final Color color;
  final String label;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
    decoration: BoxDecoration(
      color: color.withValues(alpha: .1),
      borderRadius: BorderRadius.circular(999),
    ),
    child: Row(
      mainAxisSize: MainAxisSize.min,
      children: <Widget>[
        Icon(icon, size: 14, color: color),
        const SizedBox(width: 5),
        Flexible(
          child: Text(
            label,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: TextStyle(
              color: color,
              fontSize: 11.5,
              fontWeight: FontWeight.w700,
            ),
          ),
        ),
      ],
    ),
  );
}

class _FilterChip extends StatelessWidget {
  const _FilterChip({
    required this.label,
    required this.count,
    required this.color,
    required this.selected,
    required this.onSelected,
  });

  final String label;
  final int count;
  final Color color;
  final bool selected;
  final VoidCallback onSelected;

  @override
  Widget build(BuildContext context) => Material(
    color: selected ? color : color.withValues(alpha: .08),
    borderRadius: BorderRadius.circular(999),
    child: InkWell(
      borderRadius: BorderRadius.circular(999),
      onTap: onSelected,
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: <Widget>[
            Text(
              label,
              style: TextStyle(
                color: selected ? Colors.white : color,
                fontWeight: FontWeight.w700,
                fontSize: 13,
              ),
            ),
            const SizedBox(width: 6),
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 1),
              decoration: BoxDecoration(
                color: selected
                    ? Colors.white.withValues(alpha: .25)
                    : color.withValues(alpha: .16),
                borderRadius: BorderRadius.circular(999),
              ),
              child: Text(
                '$count',
                style: TextStyle(
                  color: selected ? Colors.white : color,
                  fontSize: 11,
                  fontWeight: FontWeight.w800,
                ),
              ),
            ),
          ],
        ),
      ),
    ),
  );
}

class _StatTile extends StatelessWidget {
  const _StatTile({
    required this.label,
    required this.value,
    required this.icon,
    required this.gradient,
  });

  final String label;
  final String value;
  final IconData icon;
  final LinearGradient gradient;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.fromLTRB(14, 14, 14, 14),
    decoration: BoxDecoration(
      gradient: gradient,
      borderRadius: BorderRadius.circular(20),
      boxShadow: <BoxShadow>[
        BoxShadow(
          color: gradient.colors.last.withValues(alpha: .3),
          blurRadius: 16,
          offset: const Offset(0, 8),
        ),
      ],
    ),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: <Widget>[
        Row(
          children: <Widget>[
            Icon(icon, color: Colors.white, size: 18),
            const Spacer(),
            Container(
              width: 6,
              height: 6,
              decoration: BoxDecoration(
                color: Colors.white.withValues(alpha: .7),
                shape: BoxShape.circle,
              ),
            ),
          ],
        ),
        const SizedBox(height: 12),
        FittedBox(
          fit: BoxFit.scaleDown,
          alignment: Alignment.centerLeft,
          child: Text(
            value,
            maxLines: 1,
            style: const TextStyle(
              color: Colors.white,
              fontSize: 24,
              fontWeight: FontWeight.w800,
              height: 1.1,
            ),
          ),
        ),
        const SizedBox(height: 2),
        Text(
          label,
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
          style: TextStyle(
            color: Colors.white.withValues(alpha: .9),
            fontSize: 12,
            fontWeight: FontWeight.w600,
          ),
        ),
      ],
    ),
  );
}

class _MetricBox extends StatelessWidget {
  const _MetricBox({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 9),
      decoration: BoxDecoration(
        color: theme.colorScheme.surface,
        borderRadius: BorderRadius.circular(13),
        border: Border.all(
          color: theme.colorScheme.outlineVariant.withValues(alpha: .8),
        ),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Text(
            label,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: theme.textTheme.labelSmall?.copyWith(
              color: theme.colorScheme.onSurfaceVariant,
              fontWeight: FontWeight.w600,
            ),
          ),
          const SizedBox(height: 2),
          FittedBox(
            fit: BoxFit.scaleDown,
            alignment: Alignment.centerLeft,
            child: Text(
              value,
              maxLines: 1,
              style: theme.textTheme.titleSmall?.copyWith(
                fontWeight: FontWeight.w800,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _SearchField extends StatelessWidget {
  const _SearchField({
    required this.controller,
    required this.hint,
    required this.onChanged,
  });

  final TextEditingController controller;
  final String hint;
  final ValueChanged<String> onChanged;

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);
    return TextField(
      controller: controller,
      onChanged: onChanged,
      textInputAction: TextInputAction.search,
      decoration: InputDecoration(
        hintText: hint,
        isDense: true,
        prefixIcon: const Icon(Icons.search_rounded, size: 20),
        filled: true,
        fillColor: theme.colorScheme.onSurface.withValues(alpha: .045),
        contentPadding: const EdgeInsets.symmetric(
          horizontal: 14,
          vertical: 14,
        ),
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(16),
          borderSide: BorderSide.none,
        ),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(16),
          borderSide: BorderSide.none,
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(16),
          borderSide: BorderSide(color: _Palette.indigo, width: 1.6),
        ),
      ),
    );
  }
}

class _AppBarAction extends StatelessWidget {
  const _AppBarAction({
    required this.icon,
    required this.tooltip,
    required this.onPressed,
  });

  final IconData icon;
  final String tooltip;
  final VoidCallback? onPressed;

  @override
  Widget build(BuildContext context) => IconButton(
    tooltip: tooltip,
    onPressed: onPressed,
    icon: Icon(icon, color: Colors.white),
    style: IconButton.styleFrom(
      backgroundColor: Colors.white.withValues(alpha: .18),
      shape: const CircleBorder(),
      fixedSize: const Size(38, 38),
    ),
  );
}

class _SignInField extends StatelessWidget {
  const _SignInField({
    required this.controller,
    required this.label,
    required this.icon,
    this.keyboard,
    this.obscure = false,
    this.onSubmitted,
    this.suffix,
  });

  final TextEditingController controller;
  final String label;
  final IconData icon;
  final TextInputType? keyboard;
  final bool obscure;
  final ValueChanged<String>? onSubmitted;
  final Widget? suffix;

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);
    return TextField(
      controller: controller,
      keyboardType: keyboard,
      obscureText: obscure,
      onSubmitted: onSubmitted,
      decoration: InputDecoration(
        labelText: label,
        prefixIcon: Icon(icon, size: 20),
        suffixIcon: suffix,
        filled: true,
        fillColor: theme.colorScheme.onSurface.withValues(alpha: .04),
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(14),
          borderSide: BorderSide.none,
        ),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(14),
          borderSide: BorderSide.none,
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(14),
          borderSide: const BorderSide(color: _Palette.indigo, width: 1.6),
        ),
      ),
    );
  }
}

class _ErrorBanner extends StatelessWidget {
  const _ErrorBanner({required this.message});

  final String message;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(12),
    decoration: BoxDecoration(
      color: _Palette.rose.withValues(alpha: .1),
      borderRadius: BorderRadius.circular(14),
      border: Border.all(color: _Palette.rose.withValues(alpha: .35)),
    ),
    child: Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: <Widget>[
        const Icon(Icons.error_outline_rounded, size: 18, color: _Palette.rose),
        const SizedBox(width: 10),
        Expanded(
          child: Text(
            message,
            style: const TextStyle(
              color: _Palette.rose,
              fontWeight: FontWeight.w600,
              height: 1.35,
            ),
          ),
        ),
      ],
    ),
  );
}

class _GradientButton extends StatelessWidget {
  const _GradientButton({
    required this.label,
    required this.onPressed,
    this.icon,
    this.gradient,
    this.busy = false,
  });

  final String label;
  final VoidCallback? onPressed;
  final IconData? icon;
  final LinearGradient? gradient;
  final bool busy;

  @override
  Widget build(BuildContext context) {
    final LinearGradient fill = gradient ?? _Palette.hero;
    final bool enabled = onPressed != null;
    final Widget? leading = busy
        ? const SizedBox(
            width: 18,
            height: 18,
            child: CircularProgressIndicator(
              strokeWidth: 2.2,
              color: Colors.white,
            ),
          )
        : icon == null
        ? null
        : Icon(icon, color: Colors.white, size: 20);
    return Opacity(
      opacity: enabled ? 1 : .55,
      child: DecoratedBox(
        decoration: BoxDecoration(
          gradient: fill,
          borderRadius: BorderRadius.circular(16),
          boxShadow: enabled
              ? <BoxShadow>[
                  BoxShadow(
                    color: fill.colors.first.withValues(alpha: .35),
                    blurRadius: 18,
                    offset: const Offset(0, 9),
                  ),
                ]
              : null,
        ),
        child: Material(
          color: Colors.transparent,
          child: InkWell(
            borderRadius: BorderRadius.circular(16),
            onTap: onPressed,
            child: Padding(
              padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 15),
              child: Row(
                mainAxisSize: MainAxisSize.min,
                mainAxisAlignment: MainAxisAlignment.center,
                children: <Widget>[
                  if (leading != null) ...<Widget>[
                    leading,
                    const SizedBox(width: 10),
                  ],
                  Flexible(
                    child: Text(
                      label,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        color: Colors.white,
                        fontWeight: FontWeight.w800,
                        fontSize: 15,
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}

class _GradientFab extends StatelessWidget {
  const _GradientFab({
    required this.icon,
    required this.label,
    required this.onPressed,
  });

  final IconData icon;
  final String label;
  final VoidCallback onPressed;

  @override
  Widget build(BuildContext context) => Container(
    decoration: BoxDecoration(
      gradient: _Palette.hero,
      borderRadius: BorderRadius.circular(20),
      boxShadow: <BoxShadow>[
        BoxShadow(
          color: _Palette.violet.withValues(alpha: .45),
          blurRadius: 20,
          offset: const Offset(0, 10),
        ),
      ],
    ),
    child: FloatingActionButton.extended(
      onPressed: onPressed,
      backgroundColor: Colors.transparent,
      elevation: 0,
      highlightElevation: 0,
      icon: Icon(icon, color: Colors.white),
      label: Text(
        label,
        style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w700),
      ),
    ),
  );
}

class _EmptyState extends StatelessWidget {
  const _EmptyState({
    required this.icon,
    required this.gradient,
    required this.title,
    required this.message,
    this.actionLabel,
    this.onAction,
  });

  final IconData icon;
  final LinearGradient gradient;
  final String title;
  final String message;
  final String? actionLabel;
  final VoidCallback? onAction;

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);
    return Center(
      child: SingleChildScrollView(
        padding: const EdgeInsets.all(28),
        child: ConstrainedBox(
          constraints: const BoxConstraints(maxWidth: 380),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: <Widget>[
              Container(
                width: 96,
                height: 96,
                decoration: BoxDecoration(
                  gradient: gradient,
                  shape: BoxShape.circle,
                  boxShadow: <BoxShadow>[
                    BoxShadow(
                      color: gradient.colors.last.withValues(alpha: .35),
                      blurRadius: 26,
                      offset: const Offset(0, 12),
                    ),
                  ],
                ),
                child: Icon(icon, size: 44, color: Colors.white),
              ),
              const SizedBox(height: 20),
              Text(
                title,
                textAlign: TextAlign.center,
                style: theme.textTheme.titleLarge?.copyWith(
                  fontWeight: FontWeight.w800,
                ),
              ),
              const SizedBox(height: 8),
              Text(
                message,
                textAlign: TextAlign.center,
                style: theme.textTheme.bodyMedium?.copyWith(
                  color: theme.colorScheme.onSurfaceVariant,
                  height: 1.45,
                ),
              ),
              if (actionLabel != null && onAction != null) ...<Widget>[
                const SizedBox(height: 22),
                _GradientButton(
                  label: actionLabel!,
                  onPressed: onAction,
                  gradient: gradient,
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }
}

class _SkeletonList extends StatefulWidget {
  const _SkeletonList();

  @override
  State<_SkeletonList> createState() => _SkeletonListState();
}

class _SkeletonListState extends State<_SkeletonList>
    with SingleTickerProviderStateMixin {
  late final AnimationController _controller = AnimationController(
    vsync: this,
    duration: const Duration(milliseconds: 1100),
  )..repeat(reverse: true);

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);
    final Color tint = theme.colorScheme.onSurface.withValues(alpha: .08);
    // SliverFillRemaining hands this child a tight height, so the placeholder
    // list scrolls rather than overflowing on short screens.
    return SingleChildScrollView(
      padding: const EdgeInsets.fromLTRB(16, 20, 16, 40),
      child: FadeTransition(
        opacity: Tween<double>(begin: .45, end: 1).animate(_controller),
        child: Column(
          children: <Widget>[
            for (int i = 0; i < 4; i++)
              Container(
                margin: EdgeInsets.only(bottom: i == 3 ? 0 : 14),
                padding: const EdgeInsets.all(16),
                decoration: BoxDecoration(
                  color: theme.colorScheme.surface,
                  borderRadius: BorderRadius.circular(24),
                  border: Border.all(
                    color: theme.colorScheme.outlineVariant.withValues(
                      alpha: .6,
                    ),
                  ),
                ),
                child: Row(
                  children: <Widget>[
                    Container(
                      width: 54,
                      height: 54,
                      decoration: BoxDecoration(
                        color: tint,
                        borderRadius: BorderRadius.circular(16),
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: <Widget>[
                          Container(
                            height: 13,
                            decoration: BoxDecoration(
                              color: tint,
                              borderRadius: BorderRadius.circular(6),
                            ),
                          ),
                          const SizedBox(height: 8),
                          FractionallySizedBox(
                            widthFactor: .6,
                            child: Container(
                              height: 10,
                              decoration: BoxDecoration(
                                color: tint,
                                borderRadius: BorderRadius.circular(6),
                              ),
                            ),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
              ),
          ],
        ),
      ),
    );
  }
}

// ---------------------------------------------------------------------------
// Create listing sheet
// ---------------------------------------------------------------------------
