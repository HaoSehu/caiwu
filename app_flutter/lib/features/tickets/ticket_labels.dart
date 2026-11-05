/// 工单字段的本地映射。
///
/// 后端 `GET /v2/client/tickets` 直接返回 Ticket 模型（无 Resource 包装），
/// 只下发原始枚举值 `status/department/priority`，不下发 `*_label`。
/// 这里维护与后端 `TicketService` 常量一致的文案（v4 控制台也做同样映射），
/// 避免状态标签显示空白。
library;

const ticketStatusLabels = <int, String>{
  0: '开启',
  1: '客户回复',
  2: '员工回复',
  3: '已关闭',
};

const ticketDepartmentLabels = <String, String>{
  'sales': '销售',
  'support': '技术支持',
  'billing': '财务',
  'abuse': '投诉',
};

const ticketPriorityLabels = <int, String>{
  1: '低',
  2: '中',
  3: '高',
  4: '紧急',
};

/// 状态文案：优先用后端下发值，缺失时按本地映射。
String ticketStatusLabel(Map<String, dynamic>? ticket) {
  final label = '${ticket?['status_label'] ?? ''}'.trim();
  if (label.isNotEmpty) return label;
  final status = _asInt(ticket?['status']);
  return ticketStatusLabels[status] ?? '未知';
}

String ticketDepartmentLabel(Map<String, dynamic>? ticket) {
  final label = '${ticket?['department_label'] ?? ''}'.trim();
  if (label.isNotEmpty) return label;
  final dept = '${ticket?['department'] ?? ''}'.trim();
  return ticketDepartmentLabels[dept] ?? dept;
}

String ticketPriorityLabel(Map<String, dynamic>? ticket) {
  final label = '${ticket?['priority_label'] ?? ''}'.trim();
  if (label.isNotEmpty) return label;
  final priority = _asInt(ticket?['priority']);
  return ticketPriorityLabels[priority] ?? '';
}

int _asInt(Object? value) {
  if (value is int) return value;
  if (value is num) return value.toInt();
  return int.tryParse('${value ?? ''}') ?? -1;
}
